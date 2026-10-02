<?php
declare(strict_types=1);

/* ==[ Manage ]============================================================================================ */

function manageInfo(string $text): string {
	return '<div class="manage-info">' . $text . '</div>
		';
}

function manageError(string $text): string {
	return '<div class="manage-error">' . $text . '</div>
		';
}

function manageDie(string $text, string $action = ''): void {
	global $loginStatus;
	$onload = '';
	switch ($action) {
	case 'bans': $onload = ' onload="document.form_bans.ip.focus();"'; break;
	case 'ipinfo': $onload = ' onload="document.form_ipinfo.ipinfo.focus();"'; break;
	case 'login': $onload = ' onload="document.form_login_staff.manage_user.focus();"'; break;
	case 'moderate': $onload = ' onload="document.form_moderate_post.moderate.focus();"'; break;
	case 'passcode': $onload = ' onload="document.form_passcode_login.passcode.focus();"'; break;
	case 'passcode_manage': $onload = ' onload="document.form_passcode_manage.block_reason.focus();"'; break;
	case 'passcode_new': $onload = ' onload="document.form_passcode_new.meta.focus();"'; break;
	case 'staffpost': $onload = ' onload="document.postform.parent.focus();"'; break;
	}
	$isAdmin = $loginStatus === 'admin';
	die(pageHeader() . '<body' . $onload . '>' .
		pageWrapper(ATOM_BOARD_DESCRIPTION, true) . (
			$loginStatus === 'disabled' ? '' : '<hr>
		<div class="panel-adminbar">
			<a class="link-button" href="?manage">Status</a>' .
			($isAdmin || $loginStatus === 'moderator' ? '
			<a class="link-button" href="?manage&bans">Bans</a>
			<a class="link-button" href="?manage&passcodes=new">Passcodes</a>' : '') . '
			<a class="link-button" href="?manage&modlog">ModLog</a>
			<a class="link-button" href="?manage&ipinfo=manage">IP info</a>
			<a class="link-button" href="?manage&moderate">Manage post</a>
			<a class="link-button" href="?manage&staffpost">Raw post</a>' .
			($isAdmin ? '
			<a class="link-button" href="?manage&rebuildall">Rebuild All</a>
			<a class="link-button" href="?manage&staff">Staff</a>' : '') . '
			<a class="link-button" href="?manage&account">Account</a>
			<a class="link-button" href="?manage&logout">Log Out</a>
		</div>
		') . '<hr>
		' . $text . '
		<hr>' . pageFooter(true));
}

function makeManageLoginForm(): string {
	return '<h2>Login</h2>
		<form name="form_login_staff" method="post" action="?manage">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Username:</div>
					<input type="text" name="manage_user" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">Password:</div>
					<input type="password" name="manage_password" required>
				</div>
				<input class="link-button" type="submit" value="Log In">
			</div>
		</form>';
}

function makePasscodeLoginForm(string $action, ?array $pass = null): string {
	if ($action === 'login') {
		return '<h2>Enter your passcode</h2>
		<form name="form_passcode_login" method="post" action="?passcode">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Passcode:</div>
					<input type="text" name="passcode" style="width: 400px;" required>
				</div>
				<input class="link-button" type="submit" value="Use Passcode">
			</div>
		</form>';
	} else if ($action === 'valid') {
		return '<center><b>You are using a valid passcode.</b><br>' .
			'<br>Issued: ' . date('d.m.Y D H:i:s', (int)$pass['issued']) .
			'<br>Expires: ' . date('d.m.Y D H:i:s', (int)$pass['expires']) .
			'<br><a href="/' . ATOM_BOARD . '/imgboard.php?passcode&logout">Log Out.</a></center>';
	}
	return '';
}

function makeAdminCreateForm(): string {
	return '<h2>Initial Setup: Create admin account</h2>
		<form method="post" action="?manage">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Admin username:</div>
					<input type="text" name="new_admin_user" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">Admin password:</div>
					<input type="password" name="new_admin_pass" required>
				</div>
				<input class="link-button" type="submit" value="Create account">
			</div>
			<div class="form-notes">
				Don\' forget to erase ATOM_ADMINPASS with empty string in settings.php<br>
				after you create the administrator account.
			</div>
		</form>';
}

function formatTimestamp(int $time): string {
	if ($time === 0) {
		return 'Never';
	}
	$diff = time() - $time;
	if ($diff < 60) {
		return 'Just now';
	}
	if ($diff < 3600) {
		return (int)floor($diff / 60) . 'm ago';
	}
	if ($diff < 86400) {
		return (int)floor($diff / 3600) . 'h ago';
	}
	return date('d.m.Y H:i', $time);
}

function makeStaffManager(string $token): string {
	$html = '<h2>Add new staff member</h2>
		<form method="post" action="?manage&staff">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Username:</div>
					<input type="text" name="add_user" required autocomplete="off">
				</div>
				<div class="form-row">
					<div class="form-row-label">Password:</div>
					<input type="password" name="add_pass" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">Role:</div>
					<select name="add_role">
						<option value="moderator">Moderator</option>
						<option value="janitor">Janitor</option>
					</select>
				</div>
				<input class="link-button" type="submit" value="Create account">
			</div>
		</form>
		<hr>
		<h2>Staff Management</h2>
		<table class="table"><thead>
			<tr>
				<th>Username</th>
				<th>Role</th>
				<th>Last Login</th>
				<th>Action</th>
			</tr></thead><tbody>';
	$staffList = getAllStaffMembers();
	foreach ($staffList as $person) {
		$lastLogin = (int)$person['last_login'];
		$username = htmlspecialchars($person['username']);
		$html .= '
			<tr>
				<td>' . $username . '</td>
				<td>' . ucfirst($person['role']) . '</td>
				<td title="' . date('Y-m-d H:i:s', $lastLogin) . '">' .
					formatTimestamp($lastLogin) . '</td>
				<td>' . (
					$person['role'] === 'admin' ? '---' :
					'<a href="?manage&staff&delete_staff=' . $person['id'] .
						'" onclick="return confirm(\'Delete ' . $username . '?\')">Delete</a>') . '</td>
			</tr>';
	}
	$html .= '
		</tbody></table>';
	return $html;
}

function makeChangePasswForm(string $token): string {
	return '<h2>Account settings (' . htmlspecialchars($_SESSION['atom_user']) . ')</h2>
		<form method="post" action="?manage&account">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">Old password:</div>
					<input type="password" name="old_pass" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">New password:</div>
					<input type="password" name="new_pass" required>
				</div>
				<div class="form-row">
					<div class="form-row-label">Confirm password:</div>
					<input type="password" name="confirm_pass" required>
				</div>
				<input class="link-button" type="submit" value="Update Password">
			</div>
		</form>';
}

/* ==[ Modlog ]============================================================================================ */

function makeModLogManager(string $token, string $startDate, string $endDate): string {
	$html = '<h2>Moderation period</h2>
		<form method="post" action="?manage&modlog">
			<input type="hidden" name="token" value="' . $token . '">
			<div class="form-container">
				<div class="form-row">
					<div class="form-row-label">From:</div>
					<input type="date" name="from" value="' . $startDate . '">
				</div>
				<div class="form-row">
					<div class="form-row-label">To:</div>
					<input type="date" name="to" value="' . $endDate . '">
				</div>
				<input class="link-button" type="submit" value="Show records">
			</div>
		</form>
		<hr>
		';
	$records = getModLogRecords((int)strtotime($startDate), (int)strtotime($endDate));
	if (empty($records)) {
		return $html . '<div class="notice">No moderation records found for the selected period.</div>';
	}
	$html .= '<h2>Modlog</h2>
		<center>Records shown: ' . count($records) . '</center>
		<table class="table"><thead>
			<tr>
				<th>Date / Time</th>
				<th>User</th>
				<th>Action</th>
			</tr>
		</thead>
		<tbody>';
	foreach ($records as $record) {
		$style = '';
		if (!empty($record['color']) && $record['color'] !== 'Black') {
			$style = ' style="color: ' . htmlspecialchars($record['color']) . '"';
		}
		$html .= '
			<tr' . $style . '>
				<td>' . date('d.m.y D H:i:s', $record['timestamp']) . '</td>
				<td>' . htmlspecialchars($record['username']) . '</td>
				<td>' . htmlspecialchars($record['action']) . '</td>
			</tr>';
	}
	return $html . '
		</tbody></table>';
}
