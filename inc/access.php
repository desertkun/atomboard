<?php
declare(strict_types=1);

/* ==[ Administration and moderation ]===================================================================== */

function checkLogin(): string {
	if (isset($_POST['manage_password'])) {
		$passw = $_POST['manage_password'];
		if (empty(getAllStaffMembers())) {
			if ($passw === ATOM_ADMINPASS && ATOM_ADMINPASS !== '') {
				$_SESSION['atom_user'] = 'TemporaryAdmin';
				$_SESSION['atom_role'] = 'admin';
			}
		} else {
			$staff = getStaffMember($_POST['manage_user'] ?? '');
			if ($staff && password_verify($passw, $staff['password_hash'])) {
				$userName = $staff['username'];
				$_SESSION['atom_user'] = $userName;
				$_SESSION['atom_role'] = $staff['role'];
				updateStaffLogin($userName);
				 // Generate CSRF token if not already present
				if (empty($_SESSION['atom_token'])) {
					$_SESSION['atom_token'] = bin2hex(random_bytes(32));
				}
				modLog(ucfirst($staff['role']) . ' login', '1', 'BlueViolet');
			}
		}
	}
	$loginStatus = $_SESSION['atom_role'] ?? 'disabled';
	if ($loginStatus === 'disabled') {
		setcookie('atom_access', '', time() - 3600, '/' . ATOM_BOARD . '/');
		unset($_COOKIE['atom_access']);
	} else {
		setcookie('atom_access', '1', time() + 2592000, '/' . ATOM_BOARD . '/'); // 30 days
	}
	return $loginStatus;
}

function isStaffPost(): bool {
	return isset($_POST['staffpost']) && checkLogin() !== 'disabled';
}

function deleteSession(): void {
	session_unset();
	session_destroy();
	setcookie('atom_access', '', time() - 3600, '/' . ATOM_BOARD . '/');
	unset($_COOKIE['atom_access']);
}

/* ==[ Passcodes ]========================================================================================= */

function isPassExpired(array $pass): bool {
	return time() > $pass['expires'];
}

function isPassBlocked(array $pass): string|false {
	if ($pass['blocked_till'] > time()) {
		return $pass['blocked_reason'];
	} else {
		return false;
	}
}

function clearPass(): void {
	$_SESSION['passcode'] = '';
	setcookie('passcode', '', -1, '/');
}

/* ==[ IP ]================================================================================================ */

function cidr2ip(string $cidr): array {
	$parts = explode('/', $cidr);
	$ip = $parts[0];
	$start = ip2long($ip);
	if ($start === false) {
		return [0, 0];
	}
	if (!isset($parts[1])) {
		return [$start, $start];
	}
	$nm = (int)$parts[1];
	$nm = max(0, min(32, $nm));
	// Calculating the range
	$mask = ~((1 << (32 - $nm)) - 1);
	$start &= $mask;
	$end = $start + (pow(2, 32 - $nm) - 1);
	return [(int)$start, (int)$end];
}

function ip2cidr(int $ipFrom, int $ipTo): string {
	if ($ipTo === $ipFrom) {
		return long2ip($ipFrom);
	}
	$range = $ipTo - $ipFrom + 1;
	if ((($range - 1) & $range) !== 0) {
		// Not a power of two
		return long2ip($ipFrom) . '/???';
	}
	$b = 32 - log($range, 2);
	return long2ip($ipFrom) . '/' . $b;
}

function getCountryCode(string $ip, ?\GeoIp2\Database\Reader $geoipReader): string {
	$countryCode = '';
	if ($ip !== '') {
		if (ATOM_GEOIP === 'geoip2') {
			if (!$geoipReader) {
				try {
					$geoipReader = new \GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb');
				} catch (\Exception $e) {
					return 'ANON';
				}
			}
			try {
				$record = $geoipReader->country($ip);
				$countryCode = (string)$record->country->isoCode;
			} catch (\GeoIp2\Exception\AddressNotFoundException $e) {
				$countryCode = 'ANON';
			}
		} else if (ATOM_GEOIP === 'geoip' && function_exists('geoip_country_code_by_name')) {
			$countryCode = (string)geoip_country_code_by_name($ip);
		}
	}
	return $countryCode ?: 'ANON';
}

// Check for dirty IP using external service - ipregistry.co
function isDirtyIP(string $ip): bool {
	$ipLookup = lookupByIP($ip);
	if ($ipLookup) {
		$ipLookupAbuser = $ipLookup['abuser'];
		$ipLookupVps = $ipLookup['vps'];
		$ipLookupProxy = $ipLookup['proxy'];
		$ipLookupTor = $ipLookup['tor'];
		$ipLookupVpn = $ipLookup['vpn'];
		$ipLookupAsType = $ipLookup['as_type'];
	} else {
		try {
			$ctx = stream_context_create(['http' => ['timeout' => 7]]); // Protection from request hanging
			$response = @url_get_contents('https://api.ipregistry.co/' . $ip . '?key=' . ATOM_IPLOOKUPS_KEY,
				false, $ctx);
			$json = json_decode($response);
			if (!$json || !isset($json->security)) {
				throw new Exception('Invalid API response');
			}
			$ipLookupSecurity = $json->security;
			$ipLookupAbuser = (int)($ipLookupSecurity->is_threat ||
				$ipLookupSecurity->is_abuser || $ipLookupSecurity->is_attacker);
			$ipLookupVps = (int)($ipLookupSecurity->is_cloud_provider);
			$ipLookupProxy = (int)($ipLookupSecurity->is_proxy);
			$ipLookupTor = (int)($ipLookupSecurity->is_tor || $ipLookupSecurity->is_tor_exit);
			$ipLookupVpn = (int)($ipLookupSecurity->is_vpn);
			$ipLookupAsType = isset($json->connection->type) ?
				strtolower($json->connection->type) : 'unknown';
			if (isset($json->carrier) && !empty($json->carrier->name)) {
				$providerName = '[Mobile] ' . $json->carrier->name;
			} else {
				$providerName = isset($json->connection->organization) ?
					$json->connection->organization : 'Unknown ISP';
			}
			storeLookupResult($ip, $ipLookupAbuser, $ipLookupVps, $ipLookupProxy, $ipLookupTor, $ipLookupVpn,
				$ipLookupAsType, $providerName);
		} catch (Exception $e) {
			return false;
		}
	}
	// EXCEPTION: If it is a real provider (ISP or mobile), ignore false Proxy/VPN flags
	if ($ipLookupAsType === 'isp' || str_starts_with($providerName, '[Mobile]')) {
		return (ATOM_IPLOOKUPS_BLOCK_ABUSER && $ipLookupAbuser) ||
			(ATOM_IPLOOKUPS_BLOCK_TOR && $ipLookupTor);
	}
	return (ATOM_IPLOOKUPS_BLOCK_ABUSER && $ipLookupAbuser) ||
		(ATOM_IPLOOKUPS_BLOCK_VPS && $ipLookupVps) ||
		(ATOM_IPLOOKUPS_BLOCK_PROXY && $ipLookupProxy) ||
		(ATOM_IPLOOKUPS_BLOCK_TOR && $ipLookupTor) ||
		(ATOM_IPLOOKUPS_BLOCK_VPN && $ipLookupVpn);
}

function checkIP(string $ip, bool $isPasscode, bool $isJson): void {
	// Check for dirty IP
	if (defined('ATOM_IPLOOKUPS_KEY') && ATOM_IPLOOKUPS_KEY && !$isPasscode && isDirtyIP($ip)) {
		$message = 'Error: Your IP ' . $ip . ' is not allowed due to abuse (proxy, Tor, VPN, VPS).';
		if ($isJson) {
			jsonDie('error', $message);
		} else {
			fancyDie($message);
		}
	}

	// Check for ban
	$ban = banByIP($ip);
	if ($ban) {
		checkForBans($ip, $ban, $isPasscode, $isJson);
	}
}

/* ==[ Captcha ]=========================================================================================== */

function checkCaptcha(): void {
	$captchaError = '';
	$isJson = isset($_GET['json']) && $_GET['json'] === '1';

	// Check for recaptcha
	if (ATOM_CAPTCHA === 'recaptcha') {
		require_once 'inc/recaptcha/autoload.php';
		$captcha = $_POST['g-recaptcha-response'] ?? '';
		$recaptcha = new \ReCaptcha\ReCaptcha(ATOM_RECAPTCHA_SECRET);
		$response = $recaptcha->verify($captcha, $_SERVER['REMOTE_ADDR']);
		if (!$response->isSuccess()) {
			$captchaError = 'Captcha error: ';
			$errCodes = $response->getErrorCodes();
			$errReason = $errCodes[0] ?? '';
			if ($errReason === 'missing-input-response') {
				$captchaError .= ' Please click the checkbox labeled "I\'m not a robot".';
			} else {
				$captchaError .= implode(';<br>', $errCodes);
			}
		}
	}

	// Check for simple captcha
	elseif (ATOM_CAPTCHA) {
		$captcha = strtolower(trim($_POST['captcha'] ?? ''));
		$captchaError = $captcha === '' ? 'Captcha error: The captcha text was not entered.' :
			($captcha !== strtolower(trim($_SESSION['atom_captcha'] ?? '')) ?
				'Captcha error: Incorrect captcha text entered, please try again.<br>' .
				'Click the image to retrieve a new captcha.' : '');
		unset($_SESSION['atom_captcha']);
	}

	// Output error if captcha check failed
	if ($captchaError) {
		if ($isJson) {
			jsonDie('error', $captchaError);
		} else {
			fancyDie($captchaError);
		}
	}
}
