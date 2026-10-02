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
