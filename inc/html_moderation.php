<?php
declare(strict_types=1);

function makeUserInfoForm(string $ip = ''): string {
	return '<h2>View user IP info</h2>
		<form name="form_ipinfo" method="get" action="?">
			<input type="hidden" name="manage" value="">
			<div class="form-container">' .
				makeIpField($ip, 'form_ipinfo', 'ipinfo') . '
				<input class="link-button" type="submit" value="Submit">
			</div>
		</form>';
}

function makeUserInfoManager(string $token, string $ip, array $posts): string {
	global $loginStatus;
	$isMod = $loginStatus === 'admin' || $loginStatus === 'moderator';
	$postsHtml = '';
	foreach ($posts as $post) {
		$postsHtml .= '
			<tr><th class="panel-adminbar">' . makePostManageButtons($token, $post) . '
			</th></tr>
			<tr><td>' . buildPost($post, false, 'ip') . '
			</td></tr>';
	}
	$ban = banByIP($ip);
	$banHtml = $ban ? makeBansTable([$ban]) : '';
	$ipLookupHtml = '';
	if (ATOM_IPLOOKUPS_KEY) {
		$ipLookup = lookupByIP($ip);
		if ($ipLookup) {
			$red = ' style="background: #ff000060;">1';
			$asType = isset($ipLookup['as_type']) ? strtolower($ipLookup['as_type']) : '';
			$typeLabels = [
				'isp'      => 'Consumer ISP',
				'hosting'  => 'Data Center / Hosting',
				'business' => 'Business Net',
				'education'=> 'Educational',
				'government'=> 'Government',
				'unknown'  => 'Unknown'];
			$ipLookupHtml = '<table class="table" style="width: auto; margin: 0 auto;">
		<thead><tr><th>Type</th><th>Status</th></tr></thead>
		<tbody>
			<tr><td>Abuser</td><td' . ($ipLookup['abuser'] ? $red : '>0') . '</td></tr>
			<tr><td>VPS</td><td' . ($ipLookup['vps'] ? $red : '>0') . '</td></tr>
			<tr><td>Proxy</td><td' . ($ipLookup['proxy'] ? $red : '>0') . '</td></tr>
			<tr><td>TOR</td><td' . ($ipLookup['tor'] ? $red : '>0') . '</td></tr>
			<tr><td>VPN</td><td' . ($ipLookup['vpn'] ? $red : '>0') . '</td></tr>
			<tr><td>Net Class</td><td>' .
				htmlspecialchars($typeLabels[$asType] ?? ucfirst($asType)) . '</td></tr>
			<tr><td>Provider</td><td>' .
				htmlspecialchars($ipLookup['provider_name'] ?? 'Unknown') . '</td></tr>
		</tbody></table>';
		} else {
			$ipLookupHtml = '<center>This IP has not yet been verified.</center>';
		}
	}
	return 	makeUserInfoForm($ip) . '
		<hr>
		<h2>Moderating IP ' . $ip . '</h2>
		<div class="form-container">' .
			getIpModBtns($token, $loginStatus, $ip) . '
		</div>
		<hr>
		<h2>Bans and warnings</h2>' .
		($ban ? '
		<table class="table"><thead>' .
			$banHtml . '
		</tbody></table>' : '
		<center>This IP has no bans or warnings.</center>') .
		(ATOM_IPLOOKUPS_KEY ? '
		<hr>
		<h2>IP Lookup</h2>
		' . $ipLookupHtml : '') . '
		<hr>
		<h2>User posts and threads</h2>' .
		($postsHtml ? '
		<table class="table-posts"><tbody>' .
			$postsHtml . '
		</tbody></table>' : '
		<center>No posts or threads from this IP on this board.</center>');
}

function makeReportPostForm(array $post): string {
	$isOp = isOp($post);
	return '<h2>Report a ' . ($isOp ? 'thread' : 'post') . ' to moderators</h2>
		<form name="form_report_post" method="post" action="?report&addreport">
			<input type="hidden" name="id" value="' . $post['id'] . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Reason:</div>
					<input type="text" name="reason" required>
				</div>
				<table><tbody>' .
					getCaptcha() . '
				</tbody></table>
				<input class="link-button" type="submit" value="Send a report">
			</div>
		</form>
		<hr>
		<h2>' . ($isOp ? 'OP-post' : 'Post') . ' view</h2>' .
		buildPost($post);
}

function makePostModForm(): string {
	return '<h2>Moderate a post</h2>
		<form name="form_moderate_post" method="get" action="?">
			<input type="hidden" name="manage" value="">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Post ID:</div>
					<input type="text" name="moderate" required>
				</div>
				<input class="link-button" type="submit" value="Submit">
			</div>
			<div class="form-notes">
				Tip: while browsing the imageboard, you can moderate a post if you\'re logged in.<br>
				Check the box next to a post and click "Delete" at the bottom of the page,
				with a blank password.
			</div>
		</form>';
}

function getPostReports(array $reports, ?\GeoIp2\Database\Reader $geoipReader): string {
	$reportsHtml = '';
	foreach ($reports as $report) {
		$ip = $report['ip'];
		$reportsHtml .= '
				<article class="reply report">
					&nbsp;' . (ATOM_GEOIP ? getCountryIcon($ip, $geoipReader) . '&nbsp;' : '') .
					getIpUserInfoLink($ip) . '
					(' . date('d.m.y D H:i:s', $report['timestamp']) . ')
					<br>
					<blockquote class="post-message">' . $report['reason'] . '</blockquote>
				</article>';
	}
	return $reportsHtml;
}

function getPostModBtn(string $token, string $label, string $description, array $params,
	string $icon = '', string $confirm = ''
): string {
	$hiddenInputs = '<input type="hidden" name="token" value="' . $token . '">';
	foreach ($params as $name => $value) {
		$hiddenInputs .= '
					<input type="hidden" name="' . htmlspecialchars($name) .
					'" value="' . htmlspecialchars((string)$value) . '">';
	}
	$iconHtml = $icon ? '<img src="/' . ATOM_BOARD . '/icons/' . $icon .
		'" width="16" height="16" style="vertical-align: -3px;"> ' : '';
	return '
			<div class="mod-row">
				<form method="post" action="?manage"' .
					($confirm ? ' onclick="return confirm(\'' . addslashes($confirm) . '\')"' : '') . '>
					' . $hiddenInputs . '
					<button type="submit" class="mod-button link-button">' . $iconHtml . $label . '</button>
				</form>
				<div class="mod-description">' . $description . '</div>
			</div>';
}

function getIpModBtns(string $token, string $loginStatus, string $ip, ?int $thrId = null): string {
	$modButtons = getPostModBtn($token,
		'Delete all',
		'Delete all posts and threads in /' . ATOM_BOARD . ' from IP ' . $ip,
		['delall' => $ip],
		'',
		'Are you sure to delete ALL POSTS AND THERADS in /' . ATOM_BOARD . ' from IP ' . $ip . '?');
	$isBanned = banByIP($ip);
	$isMod = $loginStatus === 'admin' || $loginStatus === 'moderator';
	$modButtons .= '
			<div class="mod-row">
				<form method="get" action="?">
					<input type="hidden" name="manage" value="">
					<input type="hidden" name="bans" value="' . $ip . '">' .
					(isset($thrId) ? '
					<input type="hidden" name="thrid" value="' . $thrId . '">' : '') . '
					<button type="submit" class="mod-button link-button"' .
						($isBanned || !$isMod ? ' disabled' : '') . '>' .
						($isBanned ? 'Already banned!' : 'Ban user') . '</button>
				</form>
				<div class="mod-description">' . ($isBanned ? 'Ban record exists for IP ' . $ip :
					($isMod ? 'Ban IP ' . $ip : 'Janitors can\'t ban')) . '</div>
			</div>';
	return $modButtons;
}

function makePostModManager(string $token, array $post): string {
	global $loginStatus;
	$postId = (int)$post['id'];
	$isOp = isOp($post);

	// Post modreation buttons
	$modButtons = '';
	if ($isOp) {
		$isStickied = $post['stickied'] === 1;
		$modButtons .= getPostModBtn($token,
			($isStickied ? 'Unsticky' : 'Sticky') . ' thread',
			$isStickied ? 'Return to normal state' : 'Keep at the top of the board',
			['stick' => $postId, 'setsticky' => ($isStickied ? 0 : 1)],
			'sticky.png');
		$isLocked = $post['locked'] === 1;
		$lockedValue = $isLocked ? 'Unlock' : 'Lock';
		$modButtons .= getPostModBtn($token,
			$lockedValue . ' thread',
			$lockedValue . ' for posting',
			['lock' => $postId, 'setlocked' => ($isLocked ? 0 : 1)],
			'locked.png');
		$isEndless = $post['endless'] === 1;
		$modButtons .= getPostModBtn($token,
			'Make ' . ($isEndless ? 'non-endless' : 'endless'),
			($isEndless ? 'Disable' : 'Enable') . ' endless mode for this thread',
			['endless' => $postId, 'setendless' => ($isEndless ? 0 : 1)],
			'endless.png');
	}
	$modButtons .= getPostModBtn($token,
		'Delete ' . ($isOp ? 'thread' : 'post'),
		$isOp ? 'This will delete the entire thread' : 'This will delete the post',
		['delete' => $postId]);
	$ip = $post['ip'];
	$thrId = ((int)$post['parent']) ?: $postId;
	$modButtons .= getPostModBtn($token,
		'DelAll in thread',
		'Delete all posts from IP ' . $ip . ' in thread <a href="/' . ATOM_BOARD . '/res/' . $thrId .
			'.html#' . $postId . '" target="_blank">№' . $thrId . '</a>',
		['delall' => $ip, 'thrid' => $thrId],
		'',
		'Are you sure to delete all posts from IP ' . $ip . ' in thread №' . $thrId . '?');
	$modButtons .= getIpModBtns($token, $loginStatus, $ip, $thrId);
	$passcodeNum = ATOM_PASSCODES_ENABLED ? $post['pass'] : 0;
	if ($passcodeNum) {
		$modButtons .= '
			<div class="mod-row">
				<a class="mod-button link-button" target="_blank" href="/' . ATOM_BOARD .
					'/imgboard.php?manage=&passcode=' . $passcodeNum . '&passcodes=manage">Manage passcode</a>
				<div class="mod-description">Manage passcode №' . $passcodeNum .'</div>
			</div>';
	}
	$reports = reportsByPostID($postId);
	$reportsCount = count($reports);
	if ($reportsCount) {
		$modButtons .= getPostModBtn($token,
			'Close reports',
			'Delete all related reports',
			['deletereports' => $postId]);
	}

	// Likes table
	$geoipReader = ATOM_GEOIP === 'geoip2' ?
		new GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb') : null;
	$likes = likesByPostID($postId);
	$likesHtml = '';
	foreach ($likes as $like) {
		$likeIP = $like['ip'];
		$likesHtml .= '
			<tr><td>' . (ATOM_GEOIP ? getCountryIcon($likeIP, $geoipReader) . '&nbsp;' : '') .
				getIpUserInfoLink($likeIP) . '</td></tr>';
	}

	return '<h2>Moderating ' . ($isOp ? 'thread' : 'post') . ' №' . $postId . '</h2>
		<div class="form-container">' .
			$modButtons . '
		</div>' .
		($reportsCount ? '
		<hr>
		<h2>Reports</h2>' .
		getPostReports($reports, $geoipReader) : '') . '
		<hr>
		<h2>' . ($isOp ? 'OP-post' : 'Post') . ' view</h2>' .
		buildPost($post, false, 'edit') .
		($likesHtml ? '
		<hr>
		<h2>Likes received: ' . count($likes) . '</h2>
		<table class="table"><thead>
			<tr><th>IP</th></tr>
		</thead><tbody>' .
			$likesHtml . '
		</tbody></table>' : '');
}

function getPostManageBtn(string $token, string $label, array $params,
	string $title = '', string $confirm = ''
): string {
	$inputs = '<input type="hidden" name="token" value="' . $token . '">';
	foreach ($params as $name => $value) {
		$inputs .= '
					<input type="hidden" name="' . $name . '" value="' . $value . '">';
	}
	return '
				<form method="post" action="?manage"' .
					($confirm ? ' onclick="return confirm(\'' . addslashes($confirm) . '\')"' : '') . '>
					' . $inputs . '
					<button type="submit" class="link-button" title="' . htmlspecialchars($title) . '">' .
						$label . '</button>
				</form>';
};

function makePostManageButtons(string $token, array $post): string {
	global $loginStatus;
	$postId = (int)$post['id'];
	$thrId = ((int)$post['parent']) ?: $postId;
	$ip = $post['ip'];
	$isOp = isOp($post);
	$result = '
				<a class="link-button" target="_blank" href="/' . ATOM_BOARD .
					'/imgboard.php?manage&moderate=' . $postId . '" title="Advanced options">Manage ' .
					($isOp ? 'thread' : 'post') . '</a>';
	$result .= getPostManageBtn($token,
		$isOp ? 'Delete thread' : 'Delete post',
		['delete' => $postId],
		$isOp ? 'Delete entire thread' : 'Delete post');
	$result .= getPostManageBtn($token,
		'DelAll in thread',
		['delall' => $ip, 'thrid' => $thrId],
		'Delete all posts from IP ' . $ip . ' in thread №' . $thrId,
		'Are you sure to delete all posts from IP ' . $ip . ' in thread №' . $thrId . '?');
	$result .= getPostManageBtn($token, 'Delete all', ['delall' => $ip],
		'Delete all posts and threads in /' . ATOM_BOARD . ' from IP ' . $ip,
		'Are you sure to delete ALL POSTS AND THERADS in /' . ATOM_BOARD . ' from IP ' . $ip . '?');
	if ($loginStatus === 'admin' || $loginStatus === 'moderator') {
		$isBanned = banByIP($ip);
		$result .= '
				<a class="link-button" target="_blank" href="/' . ATOM_BOARD .
					'/imgboard.php?manage=&bans=' . $ip . '&thrid=' . $thrId . '" title="' .
					($isBanned ? 'Already banned!' : 'Ban IP ' . $ip) .'">' .
					($isBanned ? 'Banned!' : 'Ban user') . '</a>';
	}
	$passcodeNum = ATOM_PASSCODES_ENABLED ? $post['pass'] : 0;
	if ($passcodeNum) {
		$result .= '
				<a class="link-button" target="_blank" href="/' . ATOM_BOARD .
					'/imgboard.php?manage=&passcode=' . $passcodeNum .
					'&passcodes=manage" title="Manage passcode №' . $passcodeNum .'">Manage passcode</a>';
	}
	return $result;
}

function makeStatusManager(string $token): string {
	global $loginStatus;

	// Build reports table
	$reports = getAllReports();
	$reportsCount = count($reports);
	$reportsHtml = '';
	if ($reportsCount) {
		$geoipReader = ATOM_GEOIP === 'geoip2' ?
			new GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb') : null;
		$reportsByPost = [];
		foreach ($reports as $report) {
			$postId = (int)$report['postnum'];
			if (!isset($reportsByPost[$postId])) {
				$reportsByPost[$postId] = [];
			}
			$reportsByPost[$postId][] = $report;
		}
		foreach (array_keys($reportsByPost) as $postId) {
			$post = getPost($postId);
			if (!$post) {
				continue;
			}
			$reportsHtml .= '
			<tr><th class="panel-adminbar">' .
				getPostManageBtn($token,
					'Close reports',
					['deletereports' => $postId],
					'Delete all related reports') .
				makePostManageButtons($token, $post) . '
			</th></tr>
			<tr><td>' .
				buildPost($post, false, 'ip') .
				getPostReports($reportsByPost[$postId], $geoipReader) . '
			</td></tr>';
		}
	}

	// Build posts requiring premoderation
	$reqModPostHtml = '';
	if (ATOM_REQMOD === 'files' || ATOM_REQMOD === 'all') {
		$reqModPosts = getLatestPosts(false, 20);
		foreach ($reqModPosts as $post) {
			$reqModPostHtml .= '
			<tr><th class="panel-adminbar">' .
				getPostManageBtn($token,
					'Approve',
					['approve' => $post['id']],
					'Allow to be published') .
				makePostManageButtons($token, $post) . '
			</th></tr>
			<tr><td>' . buildPost($post, false, 'ip') . '
			</td></tr>';
		}
	}

	// Build recent posts table
	$postsHtml = '';
	$recentCount = 100;
	$posts = getLatestPosts(true, $recentCount);
	foreach ($posts as $post) {
		$postsHtml .= '
			<tr><th class="panel-adminbar">' . makePostManageButtons($token, $post) . '
			</th></tr>
			<tr><td>' . buildPost($post, false, 'ip') . '
			</td></tr>';
	}

	// Build status page
	$threads = getThreadsCount();
	$bans = count(getAllBans());
	$uniquePostersCount = getUniquePostersCount();
	$uniquePosters = $uniquePostersCount > 0 ? $uniquePostersCount . ' unique users' : '';
	return '<h2>Status</h2>
		<center>' . $threads . ' ' . plural('thread', $threads) . ', ' . $bans . ' ' . plural('ban', $bans) .
			', ' . $reportsCount . ' ' . plural('report', $reportsCount) . ', ' . $uniquePosters .
		'</center>' .
		($reportsCount ? '
		<hr>
		<h2>Reports</h2>
		<div class="form-container">' .
			getPostModBtn($token,
				'Close all',
				'Clear all reports on the board',
				['deleteallreports' => ''],
				'',
				'Are you sure to close ALL REPORTS on /' . ATOM_BOARD . '?') . '
		</div>
		<table class="table-posts"><tbody>' .
			$reportsHtml . '
		</tbody></table>' : '') .
		((ATOM_REQMOD === 'files' || ATOM_REQMOD === 'all') && $reqModPostHtml !== '' ? '
		<hr>
		<h2>Pending posts</h2>
		<table class="table-posts"><tbody>' .
			$reqModPostHtml . '
		</tbody></table>' : '') . '
		<hr>
		<h2>Recent ' . $recentCount . ' posts</h2>
		<table class="table-posts"><tbody>' .
			$postsHtml . '
		</tbody></table>';
}
