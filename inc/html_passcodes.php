<?php
declare(strict_types=1);

function makePasscodesManager(string $token): string {
	global $loginStatus;
	$isAdmin = $loginStatus === 'admin';
	$passHtml = '';
	if ($isAdmin) {
		$passHtml .= '<h2>Issue a new passcode</h2>
		<form name="form_passcode_new" method="post" action="?manage&issuepasscode">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Passcode duration (sec):</div>
					<input type="text" name="expires" value="31536000" required>
					<div>
						<button class="link-button" onclick="document.form_passcode_new.expires.value = ' .
							'\'2592000\'; return false;">30d</button>
						<button class="link-button" onclick="document.form_passcode_new.expires.value = ' .
							'\'15780000\'; return false;">6m</button>
						<button class="link-button" onclick="document.form_passcode_new.expires.value = ' .
							'\'31536000\'; return false;">1y</button>
					</div>
				</div>
				<div class="form-row">
					<div class="form-row-label">Meta (optional info):</div>
					<input type="text" name="meta">
				</div>
				<div class="form-row">
					<div class="form-row-label">Meta for Admin (optional):</div>
					<input type="text" name="meta_admin">
				</div>' .
				(ATOM_UNIQUEID ? '
				<div class="form-row">
					<div class="form-row-label">Fixed uid name (optional):</div>
					<input type="text" name="name">
				</div>' : '') . '
				<input class="link-button" type="submit" value="Submit">
			</div>
		</form>
		<hr>
		';
	}
	$passNum = (int)($_GET['passcode'] ?? 0);
	if ($passNum > 0) {
		$editPass = passByNum($passNum);
	}
	$passHtml .= '<h2>Manage the passcode</h2>
		<form name="form_passcode_manage" method="post" action="?manage&managepasscode">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Passcode number:</div>
					<input type="text" name="id" value="' . ($passNum ?: '') . '" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">Meta (related info):</div>
					<input type="text" name="meta" value="' .
						($editPass['meta'] ?? '') . '" size="50">
				</div>' .
				(ATOM_UNIQUEID ? '
				<div class="form-row">
					<div class="form-row-label">Fixed uid name (optional):</div>
					<input type="text" name="name" value="' .
						($editPass['name'] ?? '') . '" size="50">
				</div>' : '') .
				($isAdmin ? '
				<div class="form-row">
					<div class="form-row-label">Expires:</div>
					<input type="datetime-local" name="expires" value="' .
						(isset($editPass['expires']) ? date('Y-m-d\TH:i', (int)$editPass['expires']) : '') .
						'" required>
				</div>' : '') . '
				<div class="form-row">
					<div class="form-row-label">Block till:</div>
					<input type="datetime-local" name="block_till" value="' .
						(isset($editPass['blocked_till']) && $editPass['blocked_till'] ?
							date('Y-m-d\TH:i', (int)$editPass['blocked_till']) : '') . '">
					<div>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 36E5 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">1hr</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 864E5 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">1d</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 1728E5 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">2d</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 6048E5 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">1w</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 12096E5 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">2w</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = ' .
							'new Date(Date.now() + 2592E6 - (new Date).getTimezoneOffset() * 6E4).toISOString().slice(0, 16); return false;">30d</button>
						<button class="link-button" onclick="this.parentNode.previousElementSibling.value = \'\'; return false;">unblock</button>
					</div>
				</div>
				<div class="form-row">
					<div class="form-row-label">Block reason:</div>
					<input type="text" name="block_reason" value="' .
						($editPass['blocked_reason'] ?? '') . '" size="50">
				</div>
				<input class="link-button" type="submit" value="Submit">
			</div>
		</form>';
	$passcodes = getAllPasscodes();
	$passCount = count($passcodes);
	if ($passCount > 0) {
		$passTableHtml = '';
		$nowTime = time();
		$activePassCount = 0;
		$geoipReader = ATOM_GEOIP === 'geoip2' ?
			new GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb') : null;
		foreach ($passcodes as $pass) {
			$ip = $pass['last_used_ip'];
			$passcodeNum = $pass['number'];
			$blockedTill = $pass['blocked_till'];
			$isExpired = $nowTime > $pass['expires'];
			if (!$isExpired) {
				$activePassCount++;
			}
			$passTableHtml .= '
			<tr' . ($isExpired ? ' class="passcode-expired"' :
				($nowTime < $blockedTill ? ' class="passcode-blocked"' : '')) . '>
				<td><a class="link-button" target="_blank" href="/' . ATOM_BOARD .
					'/imgboard.php?manage=&passcode=' . $passcodeNum .
					'&passcodes=manage" title="Manage passcode №' . $passcodeNum . '">' .
					$passcodeNum . '</a></td>' .
				($isAdmin ? '
				<td><input type="text" value="' . $pass['id'] . '" readonly></td>
				<td style="word-break: break-all;">' . $pass['meta_admin'] . '</td>' : '') . '
				<td style="word-break: break-all;">' .
					str_replace('[donator]', '<img class="poster-achievement" height="18" title=' .
					'"Donator" src="/' . ATOM_BOARD . '/icons/donator.png">', $pass['meta']) . '</td>' .
				(ATOM_UNIQUEID ? '
				<td>' . ($pass['name'] ?: '') . '</td>' : '') . '
				<td>' . date('d.m.Y H:i:s', (int)$pass['issued']) . '</td>
				<td>' . date('d.m.Y H:i:s', (int)$pass['expires']) . '</td>
				<td>' . ($blockedTill ? date('d.m.Y H:i:s', (int)$blockedTill) : '') . '</td>
				<td>' . $pass['blocked_reason'] . '</td>
				<td>' . ($pass['last_used'] ? date('d.m.Y H:i:s', (int)$pass['last_used']) : '') . '</td>
				<td style="white-space: nowrap;">' . ($ip ?
					(ATOM_GEOIP ? getCountryIcon($ip, $geoipReader) . '&nbsp;' : '') .
					getIpUserInfoLink($ip) : '') . '</td>
			</tr>';
		}
		$passHtml .= '
		<hr>
		<h2>Issued passcodes</h2>
		<center>Total passcodes: ' . $passCount . ', active passcodes: ' . $activePassCount . '</center>
		<div><input type="checkbox" id="show_expired" onchange="' .
			'document.querySelectorAll(\'.passcode-expired\').forEach(' .
				'el => el.style.display = this.checked ? \'\': \'none\');" checked> Show expired</div>
		<table class="table"><thead>
			<tr>
				<th>№</th>' .
				($isAdmin ? '
				<th>ID (admin)</th>
				<th>Meta (admin)</th>' : '') . '
				<th>Meta</th>' .
				(ATOM_UNIQUEID ? '
				<th>Name</th>' : '') . '
				<th>Set at</th>
				<th>Expires</th>
				<th>Blocked till</th>
				<th>Blocked reason</th>
				<th>Last used</th>
				<th>Last used IP</th>
			</tr></thead><tbody>' .
			$passTableHtml . '
		</tbody></table>';
	} else {
		$passHtml .= '
		<center>No passcodes issued yet.</center>';
	}
	return $passHtml;
}
