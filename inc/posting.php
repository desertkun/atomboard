<?php
declare(strict_types=1);

function formatPostMessage(string $message, array $atom_replace_text, array $atom_replace_rand): string {
	// Message length limit
	$messageLen = mb_strlen($message);
	if ($messageLen > ATOM_POSTING_MAXLEN) {
		fancyDie('Posting error: Your message is too long (' . $messageLen .
			' characters).<br>The maximum allowed is ' . ATOM_POSTING_MAXLEN . '.');
	}

	$msg = escapeHTML(rtrim($message));

	// [code]Block code[/code], `Inline code`
	// Temporarily cut out the code (protection from processing)
	$codePrefix = ":::ATOMCODE" . bin2hex(random_bytes(2)) . ":::";
	$codeBlocks = [];
	$msg = preg_replace_callback('/\[code\](?:\r?\n)?([\s\S]*?)\[\/code\]|`([^`\r\n]+)`/iu',
		function(array $m) use (&$codeBlocks, $codePrefix): string {
			$codeBlocksCount = count($codeBlocks);
			if ($codeBlocksCount > ATOM_POSTING_MAXCODE) {
				fancyDie('Posting error: Too many code blocks in your message (' .
					$codeBlocksCount . ').<br>The maximum allowed is ' . ATOM_POSTING_MAXCODE . '.');
			}
			$isBlock = !empty($m[1]); // $m[1] is [code], $m[2] is `inline`
			$content = $isBlock ? ($m[1] ?? '') : ($m[2] ?? '');
			if ($content === null) {
				$content = '';
			}
			// Replacing line breaks with temporary tags
			$content = str_replace(["\r\n", "\r", "\n"], '@!@LINE@!@', $content);
			// Save to a temporary array, X at the end as a stopper
			$id = $codePrefix . count($codeBlocks) . ":::";
			$codeBlocks[$id] = $isBlock ? '<pre>' . $content . '</pre>' : '<code>' . $content . '</code>';
			return $id;
		}, $msg);

	// Forced wordbreaks for long words (before the main markings)
	$breakPrefix = '@!@ATOM_WORDBREAK@!@';
	if (ATOM_WORDBREAK > 0) {
		$msg = preg_replace('/([^\s]{' . ATOM_WORDBREAK . '})(?=[^\s])/u', '$1' . $breakPrefix, $msg);
	}

	// Post >>links
	$refLinkCount = 0;
	$msg = preg_replace_callback('/&gt;&gt;([0-9]+)/u', function(array $m) use (&$refLinkCount): string {
		if (++$refLinkCount > ATOM_POSTING_MAXLINKS) {
			fancyDie('Posting error: Too many >>references to other posts in your message (' .
				++$refLinkCount . ').<br>The maximum allowed is ' . ATOM_POSTING_MAXLINKS . '.');
		}
		static $cache = [];
		$id = (int)$m[1];
		if (!isset($cache[$id])) {
			$cache[$id] = getPost($id);
		}
		if ($p = $cache[$id]) {
			return sprintf('<a class="%s" href="/%s/res/%s.html#%s">%s</a>',
				isOp($p) ? 'refop' : 'refreply', ATOM_BOARD, getThreadId($p), $id, $m[0]);
		}
		return $m[0];
	}, $msg);

	// Inline markdown and multiline BBcode
	$rules = [
		'/\*\*([^\*\r\n]+)\*\*/u'    => '<b>$1</b>', // **Bold**
		'/\*([^\*\r\n]+)\*/u'        => '<i>$1</i>', // *Italic*
		'/__([^_\r\n]+)__/u'         => '<span class="underline">$1</span>', //__Underline__
		'/~~([^~\r\n]+)~~/u'         => '<del>$1</del>', // ~~Strike~~
		'/%%([^%\r\n]+)%%/u'         => '<span class="spoiler">$1</span>', // %%Spoiler%%
		'/^(&gt;.*?)\r?\n?$/mu'      => '<span class="unkfunc">$1</span>', // > Quotes
		'/\[b\]([\s\S]*?)\[\/b\]/iu' => '<b>$1</b>', // [b]Bold[/b]
		'/\[i\]([\s\S]*?)\[\/i\]/iu' => '<i>$1</i>', // [i]Italic[/i]
		'/\[u\]([\s\S]*?)\[\/u\]/iu' => '<span class="underline">$1</span>', // [u]Underline[/u]
		'/\[s\]([\s\S]*?)\[\/s\]/iu' => '<del>$1</del>', // [s]Strike[/s]
		'/\[spoiler\]([\s\S]*?)\[\/spoiler\]/iu' =>
			'<span class="spoiler">$1</span>', // [spoiler]Spoier[/spoiler]
	];
	$msg = preg_replace(array_keys($rules), array_values($rules), $msg);

	// [Markdown links](url) and hyperlinks (with protection from javascript:)
	$urlCount = 0;
	$msg = preg_replace_callback(
		'/\[(.*?)\]\((https?:\/\/[^\s\)]+)\)|((?:f|ht)tps?:\/\/[^\s<\[]+?)(?=[,.?!:;)]?(?:\s|$|<|\[))/iu',
		function(array $m) use (&$urlCount): string {
			if (++$urlCount > ATOM_POSTING_MAXURL) {
				fancyDie('Posting error: Too many external links in your message (' .
					++$urlCount . ').<br>The maximum allowed is ' . ATOM_POSTING_MAXURL . '.');
			}
			return !empty($m[3]) ?
				'<a href="' . $m[3] . '" target="_blank">' . $m[3] . '</a>' : // 3=hyperlink
				'<a href="' . $m[2] . '" target="_blank">' . $m[1] . '</a>'; // 2=markdown URL, 1=markdown text
		}, $msg);

	// Line breaks
	$msg = str_replace(["\r\n", "\r", "\n"], '<br>', $msg);
	// Code: Restoring saved blocks back
	if (!empty($codeBlocks)) {
		$msg = strtr($msg, $codeBlocks);
	}
	// Code: Recovering line breaks in code blocks
	$msg = str_replace('@!@LINE@!@', "\r\n", $msg);

	// Handling wordbreaks in links
	if (ATOM_WORDBREAK > 0 && str_contains($msg, $breakPrefix)) {
		$msg = preg_replace_callback('/<a[^>]+>.*?<\/a>/su',
			fn(array $m): string => str_replace($breakPrefix, '', $m[0]), $msg);
		$msg = str_replace($breakPrefix, '<br>', $msg);
	}

	// Text replacement from settings.php
	if (!empty($atom_replace_text)) {
		$callbacks = [];
		foreach ($atom_replace_text as $pattern => $replacement) {
			$callbacks[$pattern] = fn(array $m): string =>
				'<span class="autoreplace" style="color: ' .
					hslToHex((float)mt_rand(0, 360), .9, .5) . ';">' .
				preg_replace_callback('/\$(\d+)/',
					fn(array $idx): string => $m[$idx[1]] ?? '', $replacement) .
				'</span>';
		}
		$msg = preg_replace_callback_array($callbacks, $msg);
	}
	if (!empty($atom_replace_rand)) {
		foreach ($atom_replace_rand as $pattern => $replacements) {
			$msg = preg_replace_callback($pattern, fn(): string =>
				'<span class="autoreplace" style="color: ' .
					hslToHex((float)mt_rand(0, 360), .9, .5) . '">' .
				$replacements[array_rand($replacements)] . '</span>', $msg);
		}
	}
	return $msg;
}

function buildPostNameblock(
	array $post, array $passcode, string $loginStatus, bool $hasAccess, bool $isAdmin, bool $isPasscode
): string {
	$pass = $post['pass'] && $passcode[1] ?
		'<img class="poster-achievement" height="18" title="Donator" src="/' .
		ATOM_BOARD . '/icons/donator.png"> ' : '';
	$nameClass = 'poster-name' .
		($hasAccess && $post['name'] ? ($isAdmin ? ' poster-name-admin' : ' poster-name-mod') : '');
	$posterName = escapeHTML(($post['name'] || $post['tripcode']) ? $post['name'] : ATOM_POSTERNAME);
	$posterTrip = $post['tripcode'] !== '' ?
		'<span class="poster-trip">!' . $post['tripcode'] . '</span>' : '';
	$postNameBlock = sprintf('%s<span class="%s">%s</span>%s', $pass, $nameClass, $posterName, $posterTrip);
	if ($hasAccess && ($post['name'] || $post['tripcode'])) {
		$roles = ['admin' => '## Admin', 'janitor' => '## Janitor', 'moderator' => '## Mod'];
		if (isset($roles[$loginStatus])) {
			$roleClass = $isAdmin ? 'poster-name-admin' : 'poster-name-mod';
			$postNameBlock .= ' <span class="' . $roleClass . '">' . $roles[$loginStatus] . '</span>';
		}
	} elseif (ATOM_UNIQUEID) {
		$ip = $post['ip'];
		$parentId = (int)$post['parent'];
		// Generate a main hash from IP for the ID and name
		$fullHash = hash_hmac('sha256', $ip . $parentId, ATOM_TRIPSEED);
		$ipHashHex = substr($fullHash, 0, 8);
		$ipHashInt = hexdec($ipHashHex);
		$uidLabel = $ipHashHex;
		if (ATOM_UNIQUENAME) {
			if($isPasscode && $passcode[2]) {
				$uidLabel = $passcode[2];
			} else {
				global $firstNames, $lastNames;
				// Generate firstname by main hash
				$fName = !empty($firstNames) ? $firstNames[$ipHashInt % count($firstNames)] : '';
				// Generate lastname by subnet /20 (using mask)
				$subnet = long2ip(ip2long($ip) & 0xFFFFF000);
				$subHashInt = hexdec(substr(hash_hmac('sha256', $subnet . $parentId, ATOM_TRIPSEED), 0, 8));
				$lName = !empty($lastNames) ? $lastNames[$subHashInt % count($lastNames)] : '';
				$uidLabel = trim($fName . ' ' . $lName) ?: $ipHashHex;
			}
		}
		$postNameBlock .= sprintf(
			' <span class="poster-uid" data-uid="%s" style="color: %s;">%s</span>',
			$ipHashHex,
			hslToHex($ipHashInt % 360, 1, .3),
			htmlspecialchars($uidLabel, ENT_QUOTES, 'UTF-8'));
	}
	if ($post['email'] !== '') {
		$lowEmail = strtolower($post['email']);
		if ($lowEmail !== 'noko') {
			$postNameBlock = sprintf('<a href="mailto:%s"%s>%s</a>',
				escapeHTML($post['email']),
				($lowEmail === 'sage' ? ' class="sage"' : ''),
				$postNameBlock);
		}
	}
	$timestamp = time();
	return sprintf('%s <time class="post-date" datetime="%s">%s</time>',
		$postNameBlock,
		date('c', $timestamp),
		date('d.m.y D H:i:s', $timestamp));
}

function validateGuestPostingAccess(bool $isPasscode, array $atom_banned_countries): void {
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

function parsePostName(string $postName): array {
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
		$name = $namePart;
	} else {
		$name = $postName;
		$tripcode = '';
	}
	$name = mb_substr($name, 0, 75);
	return [$name, $tripcode];
}

function validatePostWithoutMedia(
	array $post, array $hideFields, bool $isStaffPost, array $atom_uploads, array $atom_embeds
): void {
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
}
