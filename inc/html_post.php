<?php
declare(strict_types=1);

/* ==[ Postform ]========================================================================================== */

function getCaptcha(): string {
	return 	ATOM_CAPTCHA ? '
					<tr id="captchablock">
						<td class="postblock"></td>
						<td>' . (ATOM_CAPTCHA === 'recaptcha' ? '
							<div style="min-height: 80px;">
								<div id="g-recaptcha" class="g-recaptcha" data-sitekey="' .
									ATOM_RECAPTCHA_SITE . '"></div>
								<noscript><div>
									<div style="width: 302px; height: 422px; position: relative;">
										<div style="width: 302px; height: 422px; position: absolute;">
											<iframe src="https://www.google.com/recaptcha/api/fallback?k=' .
												ATOM_RECAPTCHA_SITE . '" frameborder="0" scrolling="no"' .
												' style="width: 302px; height:422px; border-style: none;">
											</iframe>
										</div>
									</div>
									<div style="width: 300px; height: 60px; border-style: none; bottom: 12px; left: 25px; margin: 0px; padding: 0px; right: 25px; background: #f9f9f9; border: 1px solid #c1c1c1; border-radius: 3px;">
										<textarea id="g-recaptcha-response" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid #c1c1c1; margin: 10px 25px; padding: 0px; resize: none;"></textarea>
									</div>
								</div></noscript>
							</div>
						' : '
							<input type="text" class="postform-input" name="captcha" id="captcha"' .
								' placeholder="Captcha" accesskey="c" autocomplete="off">
							<img id="captchaimage" src="/' . ATOM_BOARD . '/inc/captcha.php"' .
								' width="175" height="55" alt="CAPTCHA" onclick="reloadCaptcha();">
						') . '</td>
					</tr><tr id="validcaptchablock" style="display: none">
						<td class="postblock"></td>
						<td>
							No captcha: you are a passcode user. <a href="/' . ATOM_BOARD .
								'/imgboard.php?passcode&logout">Log Out.</a>
						</td>
					</tr><tr id="invalidcaptchablock" style="display: none">
						<td class="postblock"></td>
						<td>
							Your pass code seems to be not valid. <a href="/' . ATOM_BOARD .
								'/imgboard.php?passcode" target="_blank">Log In Again?</a>
						</td>
					</tr>' : '';
}

function supportedFileTypes(): string {
	global $atom_uploads;
	if (empty($atom_uploads)) {
		return '';
	}
	$typesAllowed = array_map('strtoupper', array_unique(array_column($atom_uploads, 0)));
	$typesLast = array_pop($typesAllowed);
	$typesFormatted = $typesAllowed ? implode(', ', $typesAllowed) . ' and ' . $typesLast : $typesLast;
	return 'Supported file type' . (count($atom_uploads) !== 1 ? 's are ' : ' is ') . $typesFormatted . '.';
}

function buildPostForm(int $parent, bool $isStaffPost = false): string {
	global $atom_hidefieldsop, $atom_hidefields, $atom_uploads, $atom_embeds;
	$isInThread = $parent !== 0;
	$hideFields = $isInThread ? $atom_hidefields : $atom_hidefieldsop;
	$postformExtra = ['name' => '', 'email' => '', 'subject' => '', 'footer' => ''];
	$inputSubmit = '<input type="submit" value="' .
		($isStaffPost ? 'New post' : ($isInThread ? 'Reply' : 'New thread')) . '" accesskey="z">';
	if ($isStaffPost || !in_array('subject', $hideFields)) {
		$postformExtra['subject'] = $inputSubmit;
	} elseif (!in_array('email', $hideFields)) {
		$postformExtra['email'] = $inputSubmit;
	} elseif (!in_array('name', $hideFields)) {
		$postformExtra['name'] = $inputSubmit;
	} else {
		$postformExtra['footer'] = $inputSubmit;
	}

	// Build board rules
	$maxFileSizeInputHtml = '';
	$maxFileSizeRulesHtml = '';
	$fileTypesHtml = '';
	$fileInputHtml = '';
	$embedInputHtml = '';
	if (!empty($atom_uploads) && ($isStaffPost || !in_array('file', $hideFields))) {
		if (ATOM_FILE_MAXKB > 0) {
			$maxFileSize = ATOM_PASSCODES_ENABLED ? max(ATOM_FILE_MAXKB, ATOM_FILE_MAXKB_PASS) :
				ATOM_FILE_MAXKB;
			$maxFileSizeInputHtml = '<input type="hidden" name="MAX_FILE_SIZE" value="' .
				strval($maxFileSize * 1024) . '">';
			$maxFileSizeRulesHtml = '<li>Limit: ' . ATOM_FILES_COUNT . ' ' .
				plural('file', ATOM_FILES_COUNT) . ', ' . ATOM_FILE_MAXKBDESC . ' per file' .
				(ATOM_PASSCODES_ENABLED ? ' (' . ATOM_FILE_MAXKBDESC_PASS . ' for <a href="/' . ATOM_BOARD .
					'/imgboard.php?passcode">Passcode users</a>)' : '') . '.</li>';
		}
		$fileTypesHtml = '<li>' . supportedFileTypes() . '</li>';
		$fileInputHtml = '<tr>
						<td class="postblock"></td>
						<td><input type="file" name="file[]" size="35" accesskey="f" multiple></td>
					</tr>';
	}
	if (!empty($atom_embeds) && ($isStaffPost || !in_array('embed', $hideFields))) {
		$embedInputHtml = '<tr>
						<td class="postblock"></td>
						<td><input type="text" class="postform-input" name="embed"' .
							' placeholder="YouTube URL" accesskey="x" autocomplete="off"></td>
					</tr>';
	}
	$reqModHtml = '';
	if (ATOM_REQMOD === 'files' || ATOM_REQMOD === 'all') {
		$reqModHtml = '<li>All posts' . (ATOM_REQMOD === 'files' ? ' with a file attached' : '') .
			' will be moderated before being shown.</li>';
	}
	$thumbnailsHtml = '';
	if (isset($atom_uploads['image/jpeg']) ||
		isset($atom_uploads['image/pjpeg']) ||
		isset($atom_uploads['image/png']) ||
		isset($atom_uploads['image/gif']) ||
		isset($atom_uploads['image/avif']) ||
		isset($atom_uploads['image/webp'])
	) {
		$thumbnailsHtml = '<li>Images greater than ' . ATOM_FILE_MAXWOP . 'x' . ATOM_FILE_MAXHOP . (
			ATOM_FILE_MAXW === ATOM_FILE_MAXWOP && ATOM_FILE_MAXH === ATOM_FILE_MAXHOP ? '' :
				' (new thread) or ' . ATOM_FILE_MAXW . 'x' . ATOM_FILE_MAXH . ' (reply)'
			) . ' will be thumbnailed.</li>';
	}
	$uniquePostersCount = getUniquePostersCount();
	$uniquePosters = $uniquePostersCount > 0 ?
		'<li>' . $uniquePostersCount . ' unique users on the board.</li>' : '';

	// Build postform
	return '<div class="postarea">
			<form name="postform" id="postform" method="post" action="/' . ATOM_BOARD .
				'/imgboard.php" enctype="multipart/form-data">
				' . $maxFileSizeInputHtml .
			(!$isStaffPost ? '
				<input type="hidden" name="parent" value="' . $parent . '">' : '') . '
				<table class="postform-table reply"><tbody>' . (
					$isStaffPost ? '
					<tr>
						<td class="postblock"></td>
						<td>
							<input type="checkbox" name="staffpost" checked style="margin: 0 auto;">
							<span style="font: 12px sans-serif;">Write message as raw HTML</span>
						</td>
					</tr>
					<tr>
						<td class="postblock"></td>
						<td><input type="text" class="postform-input" name="parent" placeholder="' .
							'Reply to (0 = new thread)" maxlength="75" accesskey="t"></td>
					</tr>' : ''
				) . (
					$isStaffPost || !in_array('name', $hideFields) ? '
					<tr>
						<td class="postblock"></td>
						<td>
							<input type="text" class="postform-input" name="name" placeholder="Name"' .
								' maxlength="75" accesskey="n"> ' .
							$postformExtra['name'] . '
						</td>
					</tr>' : ''
				) . (
					$isStaffPost || !in_array('email', $hideFields) ? '
					<tr>
						<td class="postblock"></td>
						<td>
							<input type="text" class="postform-input" name="email" placeholder="Mail"' .
								' maxlength="75" accesskey="e"> ' .
							$postformExtra['email'] . '
						</td>
					</tr>' : ''
				) . (
					$isStaffPost || !in_array('subject', $hideFields) ? '
					<tr>
						<td class="postblock"></td>
						<td style="display: flex;">
							<input type="text" class="postform-input" name="subject" placeholder="Subject"' .
								' maxlength="75" accesskey="s" style="width: 100%" autocomplete="off"> ' .
							$postformExtra['subject'] . '
						</td>
					</tr>' : ''
				) . (
					$isStaffPost || !in_array('message', $hideFields) ? '
					<tr>
						<td class="postblock"></td>
						<td>
							<textarea id="message" name="message" placeholder="Message' .
								($isInThread ? ' - reply in thread' : '') . '" accesskey="m"></textarea>
						</td>
					</tr>' : ''
				) . getCaptcha() . '
					' . $fileInputHtml . '
					' . $embedInputHtml .
				(
					$isStaffPost || !in_array('password', $hideFields) ? '
					<tr>
						<td class="postblock"></td>
						<td><input type="password" name="password" id="newpostpassword" size="8"' .
							' accesskey="p">&nbsp;Deletion password</td>
					</tr>' : ''
				) . '
					<tr>
						<td colspan="2" class="rules">
							<ul>
								' . $reqModHtml . '
								' . $fileTypesHtml . '
								' . $maxFileSizeRulesHtml . '
								' . $thumbnailsHtml . '
								' . $uniquePosters . '
							</ul>
						</td>
					</tr>' .
				(
					$postformExtra['footer'] !== '' ? '
					<tr>
						<td>&nbsp;</td>
						<td>' .
							$postformExtra['footer'] . '
						</td>
					</tr>
					' : ''
				) . '
				</tbody></table>
			</form>
		</div>';
}

/* ==[ Post ]============================================================================================== */

function buildPost(array $post, bool $isInThread = false, string $mode = ''): string {
	$isEditPost = $mode === 'edit';
	$showIP = $mode === 'ip';
	if (!isset($post['omitted'])) {
		$post['omitted'] = 0;
	}

	// Post files
	$postId = (int)$post['id'];
	$thrId = getThreadId($post);
	$isOp = isOp($post);
	$fileHtml = '';
	for ($i = 0; $i < ATOM_FILES_COUNT; $i++) {
		$fileHex = $post['file' . $i . '_hex'];
		if (!$fileHex) {
			continue;
		}
		$fileWidth = (int)$post['image' . $i . '_width'];
		$fileHeight = (int)$post['image' . $i . '_height'];
		$hasSize = $fileWidth > 0 && $fileHeight > 0;
		$fileName = $post['file' . $i];
		$fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
		$isImage = in_array($fileExt, ['jpg', 'png', 'gif', 'avif', 'webp']);
		$isVideo = in_array($fileExt, ['webm', 'mp4', 'mov']);
		$isEmbed = isEmbed($fileHex);
		$fileUrl = $isEmbed ? '#' : '/' . ATOM_BOARD . '/src/' . $fileName;
		$origName = $post['file' . $i . '_original'];
		$linkHtml = 'href="' . $fileUrl . '" target="_blank" onclick="return expandFile(event, this, \'' .
			($isEmbed ? 'embed' : ($isVideo ? 'video' : ($isImage ? 'image' : 'file'))) . '\');"' .
			($origName !== '' ? ' download="' . $origName . '"' : '');
		$fileInfoHtml = '<a class="file-fullname" ' . $linkHtml . '><span class="file-name">';
		if ($isEmbed) {
			$fileInfoHtml .= $origName . '</span></a>,&nbsp;' . $fileHex;
		} elseif ($fileName !== '') {
			$fileInfoHtml .= pathinfo($origName !== '' ? $origName : $fileName, PATHINFO_FILENAME) .
				'</span>.<span class="file-extension">' . $fileExt . '</span></a><br>' .
				$post['file' . $i . '_size_formatted'] .
				($hasSize ? ',&nbsp;' . $fileWidth . 'x' . $fileHeight : '');
		} else {
			continue;
		}
		$fileHtml .= '
						<figure class="post-file">
							<figcaption class="file-info">' .
								($isEditPost ? '
								<input type="checkbox" name="delete-file-mod[]" value="' . $i . '">' : '') . '
								' . $fileInfoHtml . '
							</figcaption>
							<div class="file-wrap"' .
								($isEmbed ? ' data-embed="' . rawurlencode($fileName) . '"' :
								($hasSize ? ' data-width="' . $fileWidth . '" data-height="' .
									$fileHeight . '"' : '')) . '>' .
								($post['thumb' . $i] !== '' ? '
								<a ' . $linkHtml . '>
									<img class="file-thumb' .
										($isVideo || $isEmbed? ' file-thumb-video' : '') .
										'" src="/' . ATOM_BOARD . '/thumb/' . $post['thumb' . $i] .
										'" width="' . $post['thumb' . $i . '_width'] .
										'" height="' . $post['thumb' . $i . '_height'] . '" alt="Thumbnail">
								</a>' :
								($isVideo /* If a video has no thumbnail (ffmpeg error) */ ? '
								<a ' . $linkHtml . '>
									<video src="' . $fileUrl . '" class="file-thumb file-thumb-video"></video>
								</a>' : '')) . '
							</div>
						</figure>';
	}

	// Truncate messages on board index pages for readability
	$message = $post['message'];
	if (!$isInThread && !$isEditPost) {
		$truncLen = 0;
		if (ATOM_TRUNC_LINES > 0 && substr_count($message, '<br>') > ATOM_TRUNC_LINES) {
			$brOffsets = strallpos($message, '<br>');
			$truncLen = $brOffsets[ATOM_TRUNC_LINES - 1];
		} elseif (ATOM_TRUNC_SIZE > 0 && strlen($message) > ATOM_TRUNC_SIZE) {
			$truncLen = ATOM_TRUNC_SIZE;
		}
		if ($truncLen) {
			$message = tidy_repair_string(
				substr($message, 0, $truncLen),
				['quiet' => true, 'show-body-only' => true],
				'utf8'
			) . '
						<div class="abbrev">
							Post too long. <a href="/' . ATOM_BOARD . '/res/' . $thrId . '.html#' . $postId .
							'">Click to view</a>.
						</div>';
		}
	}

	// Start post building
	$ip = $post['ip'];
	$omitted = $post['omitted'];
	$likes = $post['likes'];
	$replyBtn = $isOp && !$isInThread ? '<a class="link-button" href="res/' . $postId .
		'.html" title="Reply to thread №' . $postId . '">Reply</a>' : '';
	$reflink = '<a href="/' . ATOM_BOARD . '/res/' . $thrId . '.html#' . $postId . '"';
	$messageHtml = '';
	if ($isEditPost) {
		$token = $_SESSION['atom_token'] ?? '';
		if ($fileHtml !== '') {
			$messageHtml .= '
						<form class="post-files-edit" method="get" action="?">
							<input type="hidden" name="token" value="' . $token . '">
							<input type="hidden" name="manage" value="">
							<input type="hidden" name="delete-files" value="' . $postId . '">' .
							$fileHtml . '
							<div style="margin-left: 20px;">
								<small>Select the checkboxes and action to remove images or make spoilers:
								</small><br>
								<select name="action">
									<option value="delete" selected>Delete files</option>
									<option value="hide">Make spoilers</option>
								</select>
								<input type="submit" value="Apply to selected">
							</div>
						</form>';
		}
		$messageHtml .= '
						<form class="post-message-edit" method="post" action="?manage&editpost=' . $postId .
							'" enctype="multipart/form-data">
							<input type="hidden" name="token" value="' . $token . '">
							<textarea id="message" name="message">' .
								htmlspecialchars($message) .
							'</textarea>
							<input type="submit" value="Save message">
						</form>';
	} else {
		$messageHtml = $fileHtml . '
						<blockquote class="post-message">' .$message . '</blockquote>';
	}
	return '
				<article class="post ' . ($isOp ? 'op' : 'reply') . '" id="post' . $postId . '">
					<header class="post-meta">
						<input type="checkbox" name="delete" value="' . $postId . '">' .
						($post['subject'] !== '' ? '
						<span class="post-subject">' . escapeHTML($post['subject']) . '</span>' : '') . '
						' . (ATOM_GEOIP ? getCountryIcon($ip, null) : '') .
						($showIP || $isEditPost ? '&nbsp;' . getIpUserInfoLink($ip) : '') . '
						' . $post['nameblock'] . '
						<span class="post-id">
							' . $reflink . ' title="Click to link to post" aria-label="Link to post">№</a>
							' . $reflink . ' title="Click to reply to post" aria-label="Reply to post">' .
							$postId . '</a>
						</span>
						<span class="post-buttons">' .
							(ATOM_LIKES ? '
							<span class="like-container">
								<span class="like-icon' . ($likes ? ' like-enabled' : ' like-disabled') .
									'" onclick="sendLike(this, \'' . ATOM_BOARD . '\', ' . $postId . ');">
									<svg><use xlink:href="#symbol-like"></use></svg>
								</span><span class="like-counter">' . ($likes ? $likes : '') . '</span>
							</span>' : '') .
							($post['stickied'] === 1 ? '
							<img src="/' . ATOM_BOARD . '/icons/sticky.png"' .
								' title="Thread is stickied to top" width="16" height="16">' : '') .
							($post['locked'] === 1 ? '
							<img src="/' . ATOM_BOARD . '/icons/locked.png"' .
								' title="Thread is locked for posting" width="11" height="16">' : '') .
							($post['endless'] === 1 ? '
							<img src="/' . ATOM_BOARD . '/icons/endless.png"' .
								' title="Thread is endless" width="16" height="16">' : '') . '
							' . $replyBtn . '
						</span>
					</header>
					<div class="post-body">' .
						$messageHtml . '
					</div>
				</article>' . ($isOp && !$isInThread && $omitted > 0 ? '
				<div class="omittedposts">
					' . $omitted . ' ' . plural('post', $omitted) .
					' omitted. Click ' . $replyBtn . ' to view.
				</div>' : '');
}
