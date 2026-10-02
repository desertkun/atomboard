<?php
declare(strict_types=1);

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

	managementAccountActions($token, $isAdmin);

	managementBanReportActions($token, $isJanitor);

	managementPasscodeActions($token, $isAdmin, $isJanitor);

	managementInfoActions($token);

	managementPostActions($token);

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

function managementAccountActions(string $token, bool $isAdmin): void {
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
}

function managementBanReportActions(string $token, bool $isJanitor): void {
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
}

function managementPasscodeActions(string $token, bool $isAdmin, bool $isJanitor): void {
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
}

function managementInfoActions(string $token): void {
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
}

function managementPostActions(string $token): void {
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
}
