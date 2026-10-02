<?php
declare(strict_types=1);

// Uncomment to show debugging errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Store session cookie for 30 days
ini_set('session.gc_maxlifetime', 2592000); 
session_set_cookie_params(2592000); 
session_start();
setcookie(session_name(), session_id(), time() + 2592000);

ob_implicit_flush();
if (function_exists('ob_get_level')) {
	while (ob_get_level() > 0) {
		ob_end_flush();
	}
}

/* ==[ Utils ]============================================================================================= */

function fancyDie(string $message): void {
	$referer = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : '';
	header('Content-Type: text/html; charset=utf-8');
	die('<!DOCTYPE html>
<html lang="en" data-theme="' . (defined('ATOM_THEME') ? htmlspecialchars(ATOM_THEME) : 'Dark') . '">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Notice</title>
	<link rel="stylesheet" type="text/css" href="' .
		(defined('ATOM_BOARD') ? '/' . htmlspecialchars(ATOM_BOARD) . '/' : '') .
		'css/atomboard.css?2026051200">
</head>
<body align="center" style="text-align: center;">
	<div class="reply notice">' . $message . '</div>
	<hr>
	<a class="link-button" href="' .
		($referer ?: 'javascript:history.back();') . '" title="Return to board">Return</a>
</body>
</html>');
}

function jsonDie(string $result, ?string $message = null, array $optionalData = []): void {
	header('Content-Type: application/json; charset=utf-8');
	$response = ['result' => $result];
	if ($message !== null) {
		$response['message'] = $message;
	}
	die(json_encode(array_merge($response, $optionalData), JSON_UNESCAPED_UNICODE));
}

/* ==[ Administration and moderation requests ]============================================================ */

function managementRequest(): void {
	global $loginStatus;
	$isAdmin = $loginStatus === 'admin';
	$isJanitor = $loginStatus === 'janitor';
	$token = $_SESSION['atom_token'] ?? '';

	/* --------[ CSRF checking for all POST requests ]-------- */

	if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
		!(isset($_POST['manage_password']) || isset($_POST['new_admin_pass']))
	) {
		if (empty($token) || !hash_equals($token, $_POST['token'] ?? '')) {
			fancyDie('Security error: Invalid CSRF token.<br>Try refreshing the page or re-logging in.');
		}
	}

	/* --------[ Show the login form or the post report form if not logged ]-------- */

	if ($loginStatus === 'disabled') {
		if (is_numeric($_GET['moderate'] ?? null)) {
			$id = (int)$_GET['moderate'];
			$post = getPost($id);
			if (!$post) {
				fancyDie('Report error: Post № ' . $id . ' does not exist.');
			}
			manageDie(makeReportPostForm($post));
		}
		manageDie(makeManageLoginForm(), 'login');
	}

	/* --------[ Create an admin account if logged in with a temporary password ]-------- */

	if ($isAdmin && $_SESSION['atom_user'] === 'TemporaryAdmin') {
		if (isset($_POST['new_admin_user'], $_POST['new_admin_pass'])) {
			addStaffMember($_POST['new_admin_user'], $_POST['new_admin_pass'], 'admin');
			session_destroy();
			manageDie(manageInfo(
				'Admin account created! Please <a href="?manage">log in</a> with new credentials.'));
		}
		manageDie(manageError('No admins found in the database.') . makeAdminCreateForm());
	}

	/* --------[ Manage staff accounts ]-------- */

	if (isset($_GET['staff']) && $isAdmin) {
		$msg = '';
		// Add account
		if (isset($_POST['add_user'], $_POST['add_pass'], $_POST['add_role'])) {
			$role = $_POST['add_role'];
			if (in_array($role, ['moderator', 'janitor'])) {
				if (addStaffMember($_POST['add_user'], $_POST['add_pass'], $role)) {
					$msg = manageInfo('Staff account added for user "' .
						htmlspecialchars($_POST['add_user']) . '" with "' . $role . '" role.');
				} else {
					$msg = manageError('Adding user error: User "' .
						htmlspecialchars($_POST['add_user']) . '" already exists!');
				}
			}
		}
		// Delete account
		if (is_numeric($_GET['delete_staff'] ?? null)) {
			deleteStaffMember($_GET['delete_staff']);
			$msg = manageInfo('Staff account with ID ' . $_GET['delete_staff'] . ' has been deleted.');
		}
		manageDie($msg . makeStaffManager($token), 'staff');
	}

	/* --------[ Change account password ]-------- */

	if (isset($_GET['account'], $_SESSION['atom_user'])) {
		$msg = '';
		if (isset($_POST['old_pass'], $_POST['new_pass'], $_POST['confirm_pass'])) {
			$newPassw = $_POST['new_pass'];
			if ($newPassw === $_POST['confirm_pass']) {
				if (strlen($newPassw) >= 8) {
					$userName = $_SESSION['atom_user'];
					$user = getStaffMember($userName);
					if ($user && password_verify($_POST['old_pass'], $user['password_hash'])) {
						changeStaffMember($userName, $newPassw);
						deleteSession();
						manageDie(manageInfo('Password changed successfully!' .
							' Please <a href="?manage">log in</a> with new credentials.'));
					} else {
						$msg = 'Changing password error: Old password is incorrect!';
					}
				} else {
					$msg = 'Changing password error: New password must be at least 8 characters long!';
				}
			} else {
				$msg = 'Changing password error: New password and confirmation do not match!';
			}
		}
		manageDie(($msg ? manageError(htmlspecialchars($msg)) : '') . makeChangePasswForm($token));
	}

	/* --------[ Rebuild all posts ]-------- */

	if (isset($_GET['rebuildall']) && $isAdmin) {
		$getThreads = getThreads();
		foreach ($getThreads as $thread) {
			rebuildThreadPage((int)$thread['id']);
		}
		rebuildIndexPages();
		deleteOldLookups();

		// Delete likes for deleted posts
		$likes = getAllLikes();
		foreach ($likes as $like) {
			$id = (int)$like['postnum'];
			if (!getPost($id)) {
				deleteLikes($id);
			}
		}

		// Delete reports for deleted posts
		$reports = getAllReports();
		foreach ($reports as $report) {
			$id = (int)$report['postnum'];
			if (!getPost($id)) {
				deleteReports($id);
			}
		}

		manageDie(manageInfo('The board has been rebuilt.'));
	}

	/* --------[ Show the ban form and the list of bans ]-------- */

	if (isset($_GET['bans']) && !$isJanitor) {
		clearExpiredBans();
		$bansHtml = '';
		if (!empty($_POST['ip'])) {
			$ip = $_POST['ip'];
			$postExpire = (int)($_POST['expire'] ?? 0);
			$reason = $_POST['reason'];
			$threadId = (int)($_POST['thrid'] ?? 0);
			if (banByIP(long2ip(cidr2ip($ip)[0] ?? 0))) {
				manageDie(manageError('Ban error: IP ' . $ip . ' is already banned.'));
			}
			if ($postExpire === 1) {
				$expire = 1;
				$expireType = 'warning';
			} elseif ($postExpire > 0) {
				$expire = time() + $postExpire;
				$expireType = 'till ' . date('d.m.y D H:i:s', $expire);
			} else {
				$expire = 0;
				$expireType = 'permanent';
			}
			if (!insertBan($ip, $expire, $reason)) {
				manageDie(manageError('Ban error: Failed to add a ban record for IP ' . $ip . '.'));
			}
			modLog('Ban record added for ' . $ip . ' (' . $expireType . '). Reason: ' . $reason);
			$bansHtml = 'Ban record added for ' . $ip . ' (' . $expireType . '). Reason: ' .
				htmlspecialchars($reason) . '.';
			if (isset($_POST['ban_delall'])) {
				// If thrid = 0, pass null to delete across the board
				$deletedCount = deleteAllPosts($ip, $threadId ?: null);
				$bansHtml .= '<br>Posts are deleted: №' . $deletedCount . '.';
			}
		} elseif (is_numeric($_GET['lift'] ?? null)) {
			$id = (int)$_GET['lift'];
			$ban = banByID($id);
			if ($ban) {
				$ip = ip2cidr((int)$ban['ip_from'], (int)$ban['ip_to']);
				deleteBan($id);
				modLog('Ban record lifted for ' . $ip);
				$bansHtml = 'Ban record lifted for ' . $ip;
			}
		}
		manageDie(($bansHtml ? manageInfo($bansHtml) : '') . makeBansManager($token), 'bans');
	}

	/* --------[ Close reports for the post ]-------- */

	if (is_numeric($_POST['deletereports'] ?? null)) {
		$id = (int)$_POST['deletereports'];
		deleteReports($id);
		manageDie(manageInfo('Post №' . $id . ' has been approved. Related reports are closed.'));
	}

	/* --------[ Close all reports on the board ]-------- */
	
	if (isset($_POST['deleteallreports'])) {
		$reports = getAllReports();
		if (count($reports)) {
			foreach ($reports as $report) {
				deleteReports((int)$report['postnum']);
			}
			manageDie(manageInfo('All reports on the board /' . ATOM_BOARD . ' are closed.'));
		}
	}

	/* --------[ Show the passcodes form ]-------- */

	if (ATOM_PASSCODES_ENABLED && isset($_GET['passcodes']) && !$isJanitor) {
		if ($_GET['passcodes'] === 'new') {
			manageDie(makePasscodesManager($token), 'passcode_new');
		} else if ($_GET['passcodes'] === 'manage') {
			manageDie(makePasscodesManager($token), 'passcode_manage');
		}
	}

	/* --------[ Issue a new passcode ]-------- */

	if (ATOM_PASSCODES_ENABLED && isset($_GET['issuepasscode']) && $isAdmin) {
		if (!empty($_POST['expires'])) {
			manageDie(manageInfo('New passcode issued:<br>' .
				insertPass((int)$_POST['expires'], $_POST['meta'], $_POST['meta_admin'],
					$_POST['name'] ?: null)) .
				makePasscodesManager($token));
		}
	}

	/* --------[ Manage a passcode ]-------- */

	if (ATOM_PASSCODES_ENABLED && isset($_GET['managepasscode']) && !$isJanitor) {
		$id = (int)$_POST['id'];
		$expires = isset($_POST['expires']) ?
			(!empty($_POST['expires']) ? (int)strtotime($_POST['expires']) : 0) : null;
		changePass(
			$id,
			(string)$_POST['meta'],
			isset($_POST['name']) ? (string)$_POST['name'] : null,
			$expires,
			!empty($_POST['block_till']) ? (int)strtotime($_POST['block_till']) : 0,
			(string)$_POST['block_reason']);
		manageDie(manageInfo('Passcode ' . $id . ' has been changed.') . makePasscodesManager($token));
	}

	/* --------[ Show the moderation log ]-------- */

	if (isset($_GET['modlog'])) {
		manageDie(makeModLogManager($token,
			$_POST['from'] ?? date("Y-m-d", strtotime("-30 day")),
			$_POST['to'] ?? date("Y-m-d", strtotime("+1 day"))));
	}
	
	/* --------[ View all posts from IP ]-------- */

	if (isset($_GET['ipinfo'])) {
		$ip = $_GET['ipinfo'];
		if ($ip === 'manage') {
			manageDie(makeUserInfoForm(), 'ipinfo');
		}
		manageDie(makeUserInfoManager($token, $ip, getPostsByIP($ip)));
	}

	/* --------[ Delete a post or thread ]-------- */

	if (is_numeric($_POST['delete'] ?? null)) {
		$id = (int)$_POST['delete'];
		$post = getPost($id);
		if (!$post) {
			manageDie(manageError('Deleting error: Post № ' . $id . ' does not exist.'));
		}
		deletePost($id);
		if (isOp($post)) {
			modLog('Deleted thread №' . $id . '.');
		} else {
			$thrId = (int)$post['parent'];
			rebuildThreadPage($thrId);
			modLog('Deleted post №' . $id . ' in thread №' . $thrId . '.');
		}
		rebuildIndexPages();
		manageDie(manageInfo('Post №' . $id . ' has been deleted.'));
	}

	/* --------[ Delete all posts from IP ]-------- */

	if (isset($_POST['delall'])) {
		$ip = $_POST['delall'];
		if (is_numeric($_POST['thrid'] ?? null)) {
			$thrid = (int)$_POST['thrid'];
			manageDie(manageInfo('Posts from IP ' . $ip . ' in thread №' . $thrid .
				' have been deleted: №' . deleteAllPosts($ip, $thrid) . '.'));
		} else {
			manageDie(manageInfo('Posts from IP ' . $ip . ' have been deleted: №' .
				deleteAllPosts($ip, null) . '.'));
		}
	}

	/* --------[ Delete/hide images ]-------- */

	if (is_numeric($_GET['delete-files'] ?? null)) {
		if (!isset($_GET['delete-file-mod'], $_GET['action'])) {
			manageDie(manageError('File deleting error: No files or actions selected.'));
		}
		$id = (int)$_GET['delete-files'];
		$post = getPost($id);
		if (!$post) {
			manageDie(manageError('File deleting error: Post № ' . $id . ' does not exist.'));
		}
		$thrId = getThreadId($post);
		if ($_GET['action'] === 'delete') {
			deletePostImages($post, $_GET['delete-file-mod']);
			rebuildThread($thrId);
			modLog('Deleted image(s) of ' . (isOp($post) ? 'OP-post in thread №' . $id :
				'post №' . $id . ' in thread №' . $thrId) . '.');
			manageDie(manageInfo('Selected images from post №' . $id . ' have been deleted.'));
		}
		if ($_GET['action'] === 'hide') {
			hidePostImages($post, $_GET['delete-file-mod']);
			rebuildThread($thrId);
			modLog('Hidden thumbnail(s) of ' . (isOp($post) ? 'OP-post in thread №' . $id :
				'post №' . $id . ' in thread №' . $thrId) . '.');
			manageDie(manageInfo('Thumbnails for selected images from post №' . $id . ' have been changed.'));
		}
	}

	/* --------[ Edit a message in post ]-------- */
	if (is_numeric($_GET['editpost'] ?? null) && isset($_POST['message'])) {
		$id = (int)$_GET['editpost'];
		$post = getPost($id);
		if (!$post) {
			manageDie(manageError('Post editing error: Post № ' . $id . ' does not exist.'));
		}
		$thrId = getThreadId($post);
		editPostMessage($id, $_POST['message'] . '<br><br><span style="color: purple;">Message edited: ' .
			date('d.m.y D H:i:s', time()) . '</span>');
		rebuildThread($thrId);
		modLog('Edited message of ' . (isOp($post) ? 'OP-post in thread №' . $id :
			'post №' . $id . ' in thread №' . $thrId) . '.');
		manageDie(manageInfo('The message in post №' . $id . ' has been changed.'));
	}

	/* --------[ Approve a post if premoderation enabled (see ATOM_REQMOD) ]-------- */

	if (is_numeric($_POST['approve'] ?? null)) {
		$id = (int)$_POST['approve'];
		$post = getPost($id);
		if (!$post) {
			manageDie(manageError('Approval error: Post № ' . $id . ' does not exist.'));
		}
		$thrId = getThreadId($post);
		approvePost($id);
		updateThreadPosts($thrId, $post);
		rebuildThread($thrId);
		manageDie(manageInfo('Post №' . $id . ' has been approved.'));
	}

	/* --------[ Show the post moderation form ]-------- */

	if (isset($_GET['moderate'])) {
		$id = (int)$_GET['moderate'];
		if (is_numeric($id) && $id > 0) {
			$post = getPost($id);
			if (!$post) {
				manageDie(manageError('Moderation error: Post № ' . $id . ' does not exist.'));
			}
			manageDie(makePostModManager($token, $post));
		}
		manageDie(makePostModForm(), 'moderate');
	}

	/* --------[ Stick a thread ]-------- */

	if (is_numeric($_POST['stick'] ?? null) && is_numeric($_POST['setsticky'] ?? null)) {
		$id = (int)$_POST['stick'];
		$post = getPost($id);
		if (!$post || !isOp($post)) {
			manageDie(manageError('Sticking error: Thread № ' . $id . ' does not exist.'));
		}
		$isStickied = (int)$_POST['setsticky'];
		toggleStickyThread($id, $isStickied);
		rebuildThread($id);
		$stickiedText = $isStickied === 1 ? 'stickied' : 'un-stickied';
		modLog(ucfirst($stickiedText) . ' thread №' . $id . '.');
		manageDie(manageInfo('Thread №' . $id . ' has been ' . $stickiedText . '.'));
	}

	/* --------[ Lock a thread ]--------= */

	if (is_numeric($_POST['lock'] ?? null) && is_numeric($_POST['setlocked'] ?? null)) {
		$id = (int)$_POST['lock'];
		$post = getPost($id);
		if (!$post || !isOp($post)) {
			manageDie(manageError('Locking error: Thread № ' . $id . ' does not exist.'));
		}
		$isLocked = (int)$_POST['setlocked'];
		toggleLockThread($id, $isLocked);
		rebuildThread($id);
		$lockedText = $isLocked === 1 ? 'locked' : 'un-locked';
		modLog(ucfirst($lockedText) . ' thread №' . $id . '.');
		manageDie(manageInfo('Thread №' . $id . ' has been ' . $lockedText . '.'));
	}

	/* --------[ Make an endless thread ]-------- */

	if (is_numeric($_POST['endless'] ?? null) && is_numeric($_POST['setendless'] ?? null)) {
		$id = (int)$_POST['endless'];
		$post = getPost($id);
		if (!$post || !isOp($post)) {
			manageDie(manageError('Endless thread error: Thread № ' . $id . ' does not exist.'));
		}
		$isEndless = (int)$_POST['setendless'];
		toggleEndlessThread($id, $isEndless);
		rebuildThread($id);
		$endlessText = $isEndless === 1 ? 'made endless' : 'made non-endless';
		modLog(ucfirst($endlessText) . ' thread №' . $id . '.');
		manageDie(manageInfo('Thread №' . $id . ' has been ' . $endlessText . '.'));
	}

	/* --------[ Raw post sending ]-------- */

	if (isset($_GET['staffpost'])) {
		manageDie(buildPostForm(0, true), 'staffpost');
	}

	/* --------[ Log out ]-------- */

	if (isset($_GET['logout'])) {
		if (!$isAdmin) {
			modLog(ucfirst($_SESSION['atom_role']) . ' logout', '1', 'BlueViolet');
		};
		deleteSession();
		header('Location: ?manage');
	}

	/* --------[ Show status for posts ]-------- */

	manageDie(makeStatusManager($token));
}

/* ==[ Posting requests ]================================================================================== */

function postingRequest(): void {
	/* --------[ Post submission check ]-------- */

	global $loginStatus, $atom_banned_countries, $atom_embeds, $atom_hidefields, $atom_hidefieldsop,
		$atom_replace_text, $atom_replace_rand, $atom_uploads;
	$hasAccess = $loginStatus !== 'disabled';
	$isAdmin = $loginStatus === 'admin';
	$passcode = checkPasscode(true);
	$isPasscode = !!$passcode[0];

	if (!$hasAccess) {
		// Checking for captcha if no passcode
		if (!$isPasscode) {
			checkCaptcha();
		}

		// Check for banned countries
		$ip = $_SERVER['REMOTE_ADDR'];
		if (ATOM_GEOIP && !empty($atom_banned_countries)) {
			$countryCode = getCountryCode($ip, ATOM_GEOIP === 'geoip2' ?
				new GeoIp2\Database\Reader('/usr/share/GeoIP/GeoLite2-Country.mmdb') : null);
			if (in_array($countryCode, $atom_banned_countries)) {
				fancyDie('Posting error: Posting from your country (' . $countryCode . ') is prohibited.');
			}
		}

		// Check for dirty IP and bans
		checkIP($ip, $isPasscode, false);

		// Check for flooding
		if (ATOM_POSTING_DELAY > 0) {
			$lastpost = getLastPostByIP();
			if ($lastpost && (time() - $lastpost['timestamp']) < ATOM_POSTING_DELAY) {
				$timeLeft = ATOM_POSTING_DELAY - (time() - $lastpost['timestamp']);
				fancyDie('Posting error: You will be able to make another post in ' . $timeLeft . ' ' .
					plural('second', $timeLeft) . '.<br>Please wait a moment before posting again.');
			}
		}
	}

	// Check for parent thread
	$parentId = (int)($_POST['parent'] ?? 0);
	if ($parentId > 0 && !isThreadExists($parentId)) {
		fancyDie('Posting error: Invalid parent thread ID supplied, unable to create a post.');
	}

	/* --------[ Filling post fields ]-------- */

	// Initialize default post fields
	$post = newPost($parentId);
	$isOp = isOp($post);
	if (!$isOp && !$hasAccess && getPost($post['parent'])['locked']) {
		fancyDie('Posting error: The thread is locked.<br>Posting in this thread is currently disabled.');
	}
	$hideFields = $isOp ? $atom_hidefieldsop : $atom_hidefields;
	$post['ip'] = $_SERVER['REMOTE_ADDR'];
	$post['pass'] = $passcode[0];
	$isStaffPost = isStaffPost();

	// Get name/tripcode
	if ($isStaffPost || !in_array('name', $hideFields)) {
		$postName = $_POST['name'] ?? '';
		// Look for the first separator (# or !)
		$delimPos = strpbrk($postName, '#!');
		if ($delimPos !== false) {
			$capDelimiter = $delimPos[0];
			[$namePart, $capPart] = explode($capDelimiter, $postName, 2);
			$tripcode = '';
			// Check for a second level (secure trip) within the tail. For example: name#cap#secure
			$capSecure = '';
			if (strpos($capPart, $capDelimiter) !== false) {
				[$cap, $capSecure] = explode($capDelimiter, $capPart, 2);
			} else {
				$cap = $capPart;
			}
			// Regular tripcode (DES crypt)
			if ($cap !== '') {
				// Convert to SJIS for compatibility with older Japanese boards
				if (function_exists('mb_convert_encoding')) {
					$cap = mb_convert_encoding($cap, 'SJIS', 'UTF-8') ?: $cap;
				}
				$cap = str_replace(['&amp;', ','], ['&', ', '], $cap);
				$salt = substr($cap . 'H.', 1, 2);
				$salt = preg_replace('/[^\.-z]/', '.', $salt);
				$salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
				$tripcode = substr(crypt($cap, $salt), -10);
			}
			// Secure tripcode (based on MD5 + SALT)
			if ($capSecure !== '') {
				if ($tripcode !== '') {
					$tripcode .= '!';
				}
				$tripcode .= '!' . substr(md5($capSecure . ATOM_TRIPSEED), 2, 10);
			}
			$post['name'] = $namePart;
			$post['tripcode'] = $tripcode;
		} else {
			$post['name'] = $postName;
			$post['tripcode'] = '';
		}
		$post['name'] = mb_substr($post['name'], 0, 75);
	}

	// Get email
	if ($isStaffPost || !in_array('email', $hideFields)) {
		$post['email'] = mb_substr($_POST['email'], 0, 75);
	}

	// Get subject
	if ($isStaffPost || !in_array('subject', $hideFields)) {
		$post['subject'] = mb_substr($_POST['subject'], 0, 100);
	}

	// Get message
	if (!in_array('message', $hideFields)) {
		$post['message'] = $_POST['message'];
	}

	if ($post['message'] && !$isStaffPost) {
		$post['message'] = formatPostMessage($post['message'], $atom_replace_text, $atom_replace_rand);
	}

	// Get password
	if ($isStaffPost || !in_array('password', $hideFields)) {
		$post['password'] = $_POST['password'] !== '' ? md5(md5($_POST['password'])) : '';
	}

	$post['nameblock'] = buildPostNameblock($post, $passcode, $loginStatus, $hasAccess, $isAdmin, $isPasscode);

	attachPostMedia($post, $hideFields, $isStaffPost, $isPasscode, $atom_embeds, $atom_uploads);

	/* --------[ No file upload ]-------- */

	if ($post['file0'] === '') {
		$allowedItems = [];
		$isAllowedToPost = $isStaffPost || !in_array('file', $hideFields);
		$isAllowedToEmbed = $isStaffPost || !in_array('embed', $hideFields);
		if (!empty($atom_uploads) && $isAllowedToPost) {
			$allowedItems[] = 'file';
		}
		if (!empty($atom_embeds) && $isAllowedToEmbed) {
			$allowedItems[] = 'embed URL';
		}
		$allowedStr = implode(' or ', $allowedItems);
		if (!ATOM_NOFILEOK && isOp($post) && !empty($allowedItems)) {
			fancyDie('Posting error: A ' . $allowedStr . ' is required to start a thread.');
		}
		if (!$isStaffPost && str_replace('<br>', '', $post['message']) === '') {
			$dieMsg = [];
			if (!in_array('message', $hideFields)) {
				$dieMsg[] = 'enter a message';
			}
			if (!empty($allowedItems)) {
				$dieMsg[] = 'upload a ' . $allowedStr;
			}
			$separator = (!in_array('message', $hideFields) && !empty($allowedItems)) ? ' and/or ' : '';
			fancyDie('Posting error: Please ' . implode($separator, $dieMsg) . '.');
		}
	}

	$slowRedirect = false;
	if (!$hasAccess && (($post['file0'] !== '' && ATOM_REQMOD === 'files') || ATOM_REQMOD === 'all')) {
		$slowRedirect = true;
		$post['moderated'] = '0';
		echo 'Your ' . (isOp($post) ? 'thread' : 'post') .
			' will be shown <b>once it has been approved</b>.<br>';
	}

	$post['likes'] = 0;
	$post['id'] = insertPost($post);

	/* --------[ Post/thread creation ]-------- */

	$redirectPath = ATOM_INDEX;
	if ($post['moderated'] === '1') {
		$id = $post['id'];
		$thrId = getThreadId($post);
		if (ATOM_POSTING_REDIRECT || strtolower($post['email']) === 'noko') {
			$redirectPath = '/' . ATOM_BOARD . '/res/' . $thrId . '.html#' . $id;
		}
		trimThreadsCount();
		rebuildThreadPage($thrId);
		updateThreadPosts($thrId, $post);
		rebuildIndexPages();
	}

	if ($slowRedirect) {
		die('<meta http-equiv="refresh" content="3; url=' . $redirectPath . '">');
	}
	header('Location: ' . $redirectPath, true, 303);
	exit();
}

/* ==[ Banned request ]==================================================================================== */
function checkForBans(string $ip, array $ban, bool $isPasscode, bool $isJson = false,
	bool $isCloseWarning = false
): void {
	$isRangeBan = $ban['ip_from'] !== $ban['ip_to'];
	// Range bans do not affect passcode users
	if ($isPasscode && $isRangeBan) {
		return;
	}
	$message = '';
	$reason = $ban['reason'] !== '' ? "\nReason: " . $ban['reason'] : '';
	if ($ban['expire'] === 1) {
		$message = 'Your IP ' . $ip . ' has been issued a warning.' . $reason . "\n\n";
		if ($isCloseWarning) {
			deleteBan($ban['id']);
			$message .= 'Please make sure you have read and understood the [rules](/rules/).' .
				"\nThis warning has been automatically removed, you may continue posting now.";
		} else {
			$message .= 'To continue posting, please visit [this page](/' .
				ATOM_BOARD . '/imgboard.php?banned).';
		}
	} elseif ($ban['expire'] === 0 || $ban['expire'] > time()) {
		$expireText = $ban['expire'] > 0 ?
			'This ban will expire ' . date('d.m.Y D H:i:s', (int)$ban['expire']) . '.' :
			'This ban is permanent and will not expire.';
		$rangeText = $isRangeBan ? "\nThis is a range ban (affects a whole subnet)." : '';
		$passcodeText = ATOM_PASSCODES_ENABLED && $isRangeBan ? "\nBy the way, [Passcode users](/" .
			ATOM_BOARD . '/imgboard.php?passcode) are not affected by range bans.' : '';
		$message = 'Your IP ' . $ip . ' has been banned on this imageboard.' . $reason .
			"\n\n" . $expireText . $rangeText . $passcodeText;
	} else {
		clearExpiredBans();
	}
	if ($message !== '') {
		if ($isJson) {
			$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
			$baseUrl = $protocol . $_SERVER['HTTP_HOST'];
			jsonDie('error', preg_replace('/\[(.*?)\]\((.*?)\)/', '$1 (' . $baseUrl . '$2)', $message));
		} else {
			// For normal output, convert \n to <br> and pseudo-BBCode to HTML links
			$message = str_replace("\n", '<br>', $message);
			$message = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $message);
			fancyDie($message);
		}
	}
}

function bannedRequest(): void {
	$ip = $_SERVER['REMOTE_ADDR'];
	$ban = banByIP($ip);
	if ($ban) {
		checkForBans($ip, $ban, !!checkPasscode()[0], false, true);
	} else {
		fancyDie('Your IP ' . $ip . ' is not banned at this time.');
	}
}

/* ==[ Deletion request ]================================================================================== */

function deletionRequest(): void {
	global $loginStatus;
	if (!is_numeric($_POST['delete'] ?? null)) {
		fancyDie('Deleting error: Tick the box next to a post and click "Delete" to delete it.');
	}
	$id = (int)$_POST['delete'];
	$post = getPost($id);
	if (!$post) {
		fancyDie('Deleting error: An invalid post ID was sent.<br>' .
			'Please go back, refresh the page, and try again.');
	}
	if ($loginStatus !== 'disabled' && $_POST['password'] === '') {
		// Redirect to post moderation page
		die('<meta http-equiv="refresh" content="0;url=' . basename($_SERVER['PHP_SELF']) .
			'?manage&moderate=' . $id . '">');
	} elseif ($post['password'] === '' || md5(md5($_POST['password'])) !== $post['password']) {
		fancyDie('Deleting error: Invalid password.');
	}
	deletePost($id);
	rebuildThread(getThreadId($post));
	fancyDie('Post №' . $id . ' has been deleted.');
}

/* ==[ Post report request ]=============================================================================== */

function reportRequest(): void {
	$ip = $_SERVER['REMOTE_ADDR'];
	$isPasscode = !!checkPasscode()[0];
	$isJson = isset($_GET['json']) && $_GET['json'] === '1';
	checkIP($ip, $isPasscode, $isJson); // Check for dirty IP and bans
	if (!isset($_GET['addreport'])) {
		return;
	}
	if (!$isPasscode) {
		checkCaptcha();
	}
	$id = (int)$_POST['id'];
	$report = insertReport($id, ATOM_BOARD, $ip, $_POST['reason']);
	if ($report) {
		if ($report === 'exists') {
			if ($isJson) {
				jsonDie('alreadysent');
			} else {
				fancyDie('Report error: You have already sent a report to post №' . $id . '!');
			}
		}
		if ($isJson) {
			jsonDie('ok');
		} else {
			fancyDie('Report to post №' . $id . ' successfully sent.');
		}
	}
	if ($isJson) {
		jsonDie('error');
	} else {
		fancyDie('Report error: An error occurred while sending the report.');
	}
}

/* ==[ Passcode requests ]================================================================================= */

function checkPasscode(bool $showMessages = false): array {
	if (!ATOM_PASSCODES_ENABLED || empty($_SESSION['passcode'])) {
		return [0];
	}
	$pass = passByID($_SESSION['passcode']);
	if (!$pass || isPassExpired($pass)) {
		clearPass();
		if ($showMessages) {
			fancyDie('Your passcode has expired on ' . date('d.m.Y D H:i:s', (int)$pass['expires']) .
				'.<br>Please issue a new passcode.');
		}
	}
	$blocked = isPassBlocked($pass);
	if ($blocked && $showMessages) {
		fancyDie('Your passcode has been blocked till ' . date('d.m.y D H:i:s', (int)$pass['blocked_till']) .
			'.<br>Reason: ' . $blocked . '.<br>Please log in again after the block expires.');
	}
	$ip = $_SERVER['REMOTE_ADDR'];
	$checkTill = $pass['last_used'] + ATOM_PASSCODES_USE_LIMIT;
	if ($checkTill > time() && $pass['last_used_ip'] !== $ip && $showMessages) {
		fancyDie('Your passcode has been used recently by another IP.<br>Please wait till ' .
			date('d.m.y D H:i:s', $checkTill) . ' and try again.');
	}
	usePass($pass['id'], $ip); // Update passcode info (last used IP)
	return $pass['number'] ? [$pass['number'], str_contains($pass['meta'], '[donator]'), $pass['name']] : [0];
}

function passcodeRequest(): void {
	// Check passcode entered in passcode login form
	if (isset($_POST['passcode'])) {
		$passId = $_POST['passcode'];
		$pass = passByID($passId);
		if (!$pass) {
			manageDie(manageError('<b>Could not log in the provided passcode:</b><br>' .
				'<br>This passcode is not found in database.'));
		}
		$blocked = isPassBlocked($pass);
		if (isPassExpired($pass)) {
			clearPass();
			manageDie(manageError('<b>Could not log in the provided passcode:</b><br>' .
				'<br>This passcode has expired on ' . date('d.m.Y D H:i:s', (int)$pass['expires']) .
				'.<br>Please issue a new passcode.'));
		} else if ($blocked) {
			clearPass();
			manageDie(manageError('<b>Could not log in the provided passcode:</b><br><br>' .
				'This passcode has been blocked till ' . date('d.m.y D H:i:s', (int)$pass['blocked_till']) .
				'.<br>Reason: ' . $blocked . '.<br>Please log in again after the block expires.'));
		}
		setcookie('passcode', '1', $pass['expires'], '/');
		$_SESSION['passcode'] = $passId;
		manageDie(manageInfo('<b>You have logged in. You may post without entering the captcha.</b>' .
			'<br>This passcode will expire on ' . date('d.m.Y D H:i:s', (int)$pass['expires'])));
	}

	// Check passcode status by imgboard.php?passcode&check
	if (isset($_GET['check'])) {
		if (isset($_SESSION['passcode'])) {
			$pass = passByID($_SESSION['passcode']);
			if ($pass && !isPassExpired($pass) && !isPassBlocked($pass)) {
				die('OK');
			}
		}
		http_response_code(403);
		die('INVALID');
	}

	// Logout from passcode
	if (isset($_GET['logout'])) {
		clearPass();
		manageDie(manageInfo('You have been logged out.'));
	}

	// Check if passcode already has effect now
	if (isset($_SESSION['passcode'])) {
		$pass = passByID($_SESSION['passcode']);
		if ($pass && !isPassExpired($pass) && !isPassBlocked($pass)) {
			manageDie(makePasscodeLoginForm('valid', $pass));
		}
	}

	// Show passcode login form
	manageDie(makePasscodeLoginForm('login'), 'passcode');
}

/* ==[ Like request ]====================================================================================== */

function likeRequest(): void {
	$ip = $_SERVER['REMOTE_ADDR'];
	checkIP($ip, !!checkPasscode()[0], true); // Check for dirty IP and bans, JSON response
	$postNum = (int)$_GET['like'];
	$result = toggleLike($postNum, $ip);
	$post = getPost($postNum);
	$post['likes'] = $result;
	rebuildThread(getThreadId($post));
	jsonDie('ok',
		$result[0] ? 'Post №' . $postNum . ' has been liked!' : 'Post №' . $postNum . ' has been unliked!',
		['likes' => $result[1]]);
}

/* ==[ Main ]============================================================================================== */

// Settings initialization
if (!file_exists('settings.php')) {
	fancyDie('Settings error: settings.php file not found.' .
		'<br>Please copy settings.default.php to settings.php.');
}
require 'settings.php';
if (ATOM_GEOIP === 'geoip2') {
	require 'vendor/autoload.php';
}
if (ATOM_TRIPSEED === '') {
	fancyDie('Settings error: ATOM_TRIPSEED must be configured in settings.php.');
}
if (ATOM_CAPTCHA === 'recaptcha' && (ATOM_RECAPTCHA_SITE === '' || ATOM_RECAPTCHA_SECRET === '')) {
	fancyDie('Settings error: ATOM_RECAPTCHA_SITE and ATOM_RECAPTCHA_SECRET' .
		' must be configured in settings.php.');
}

// Check if directories are writable by the script
foreach (['res', 'src', 'thumb'] as $dir) {
	if (!is_writable($dir)) {
		fancyDie('Error: Directory "' . $dir . '" can not be written to.<br>Please modify its permissions.');
	}
}

// Dynamic connection of PHP scripts
$incPath = __DIR__ . '/inc/';
$includes = [$incPath . 'functions.php', $incPath . 'html.php', $incPath . 'posting.php', $incPath . 'media.php'];
if (in_array(ATOM_DBMODE, ['mysqli', 'pdo'])) {
	$includes[] = $incPath . 'database_' . ATOM_DBMODE . '.php';
} else {
	fancyDie('Settings error: Unknown database mode in ATOM_DBMODE specified in settings.php.');
}
if (defined('ATOM_UNIQUENAME') && ATOM_UNIQUENAME) {
	$namesDir = $incPath . 'usernames/' . ATOM_UNIQUENAME . '/';
	$includes[] = $namesDir . 'firstnames.php';
	$includes[] = $namesDir . 'lastnames.php';
}
foreach ($includes as $file) {
	if (!file_exists($file)) {
		fancyDie('Error: Critical file missing: "' . basename($file) . '".');
	}
	require_once $file;
}
if (ATOM_TIMEZONE !== '') {
	date_default_timezone_set(ATOM_TIMEZONE);
}

// Check for login status [admin/moderator/janitor/disabled]
$loginStatus = checkLogin();

// Requests processing
if (isset($_GET['manage'])) {
	managementRequest();
}
if (isset($_GET['delete'])) { // Must be before postingRequest()
	deletionRequest();
}
if (array_intersect_key($_POST,
	array_flip(['name', 'email', 'subject', 'message', 'file', 'embed', 'password']))
) {
	postingRequest();
}
if (isset($_GET['banned'])) {
	bannedRequest();
}
if (isset($_GET['report'])) {
	reportRequest();
}
if (ATOM_PASSCODES_ENABLED && isset($_GET['passcode'])) {
	passcodeRequest();
}
if (isset($_GET['like'])) {
	likeRequest();
}
if (isset($_GET['ban_reasons'])) {
	header('Content-Type: application/json; charset=utf-8');
	die(json_encode($atom_ban_reasons, JSON_UNESCAPED_UNICODE));
}

// Initialization of empty board 
if (!file_exists(ATOM_INDEX) || getThreadsCount() === 0) {
	rebuildIndexPages();
}
header('Location: ' . ATOM_INDEX, true, 307);
exit();
