<?php
declare(strict_types=1);

function makeBansTable(array $bans): string {
	$geoipReader = ATOM_GEOIP === 'geoip2' ?
		new GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb') : null;
	$bansHtml = '
		<table class="table"><thead>
			<tr>
				<th>IP address</th>
				<th>Set at</th>
				<th>Expires</th>
				<th>Reason provided</th>
				<th>&nbsp;</th>
			</tr>
		</thead><tbody>';
	foreach ($bans as $ban) {
		$expire = (int)$ban['expire'];
		if ($expire === 1) {
			$expireText = 'Warning';
		} else if ($expire > 0) {
			$expireText = date('d.m.Y D H:i:s', $expire);
		} else {
			$expireText = 'Does not expire';
		}
		$ipFrom = (int)$ban['ip_from'];
		$ipTo = (int)$ban['ip_to'];
		$bansHtml .= '
			<tr>
				<td style="white-space: nowrap;">' .
					(ATOM_GEOIP ? getCountryIcon(long2ip($ipFrom), $geoipReader) . '&nbsp;' : '') .
					getIpUserInfoLink(ip2cidr($ipFrom, $ipTo)) . '</td>
				<td>' . date('d.m.Y D H:i:s', (int)$ban['timestamp']) . '</td>
				<td>' . $expireText . '</td><td>' . ($ban['reason'] !== '' ?
					htmlentities($ban['reason'], ENT_QUOTES, 'UTF-8') : '&nbsp;') . '</td>
				<td><a href="?manage&bans&lift=' . $ban['id'] . '">lift</a></td>
			</tr>';
	}
	return $bansHtml . '
		</tbody></table>';
}

function makeIpField(string $ip, string $formName, string $fieldName): string {
	return '
				<div class="form-row">
					<div class="form-row-label">IP address (CIDR format):</div>
					<input type="text" name="' . $fieldName . '" value="' . $ip .
						'" placeholder="0.0.0.0" required>
					<div>
						<button class="link-button" onclick="var el = document.' . $formName . '.' .
						$fieldName . '; el.value = el.value.split(\'/\')[0] + \'/24\'; return false;">
							subnet /24</button>
						<button class="link-button" onclick="var el = document.' . $formName . '.' .
						$fieldName . '; el.value = el.value.split(\'/\')[0] + \'/16\'; return false;">
							subnet /16</button>
					</div>
				</div>';
}

function makeBansManager(string $token): string {
	global $atom_ban_reasons;
	$banReasons = '';
	if (!empty($atom_ban_reasons)) {
		$banReasonsLen = count($atom_ban_reasons);
		for ($i = 0; $i < $banReasonsLen; $i++) {
			$banReasons .= '
						<option value="' . $atom_ban_reasons[$i] . '">' .
							$atom_ban_reasons[$i] . '</option>';
		}
	}
	$bans = getAllBans();
	$bansCount = count($bans);
	$bansHtml = '<h2>Ban an IP address</h2>
		<form name="form_bans" method="post" action="?manage&bans">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">' .
				makeIpField($_GET['bans'], 'form_bans', 'ip') . '
				<div class="form-row">
					<div class="form-row-label">Expire (sec):</div>
					<input type="text" name="expire" value="0">
					<div>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'3600\'; return false;">1hr</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'86400\'; return false;">1d</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'172800\'; return false;">2d</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'604800\'; return false;">1w</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'1209600\'; return false;">2w</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'2592000\'; return false;">30d</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'0\'; return false;">never</button>
						<button class="link-button" onclick="document.form_bans.expire.value = ' .
							'\'1\'; return false;">warning</button>
					</div>
				</div>
				<div class="form-row">
					<div class="form-row-label">Reason (optional):</div>
					<input type="text" name="reason">' .
					($banReasons ? '
					<select onchange="var el = document.form_bans.reason; el.value = this.value;' .
						' el.style.display = el.value ? \'none\' : \'\'">
						<option value="">- Select ban reason -</option>' .
						$banReasons . '
					</select>' : '') . '
				</div>
				<div class="form-row">
					<div class="form-row-label">Thread number (empty to delete all posts / threads):</div>
					<input type="text" name="thrid" value="' . ($_GET['thrid'] ?? '') . '">
				</div>
				<input class="link-button" type="submit"' .
					' name="ban_delall" value="Ban + DelAll" style="width: 100%;"' .
					' onclick="return confirm(\'Are you sure to ban and delete all posts?\')">
				<input class="link-button" type="submit" name="ban" value="Ban" style="width: 100%;">
			</div>
		</form>
		<hr>
		<h2>Current bans</h2>
		<center>Total bans: ' . $bansCount . '</center>';
	if ($bansCount > 0) {
		$bansHtml .= makeBansTable($bans);
	}
	return $bansHtml;
}
