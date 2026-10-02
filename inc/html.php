<?php
declare(strict_types=1);

/* ==[ Common elements ]=================================================================================== */

function getCountryIcon(string $ip, ?\GeoIp2\Database\Reader $geoipReader): string {
	$countryCode = getCountryCode(filter_var($ip, FILTER_VALIDATE_IP), $geoipReader);
	return '<img class="poster-country" title="' . $countryCode . '" src="/' . ATOM_BOARD .
		'/icons/flag-icons/' . $countryCode . '.png" alt="' . $countryCode . ' flag">';
}

function getIpUserInfoLink(string $ip): string {
	return '<a href="/' . ATOM_BOARD . '/imgboard.php?manage=&ipinfo=' . $ip .
		'" target="_blank" title="View user IP info">' . $ip . '</a>';
}

/* ==[ Page elements ]===================================================================================== */

function pageHeader(): string {
	return '<!DOCTYPE html>

<html data-theme="' . ATOM_THEME . '">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>' . ATOM_BOARD_DESCRIPTION . '</title>
	<link rel="shortcut icon" href="/' . ATOM_BOARD . '/icons/favicon.png">
	<link rel="stylesheet" type="text/css" href="/' . ATOM_BOARD . '/css/atomboard.css?2026051200">
	<script src="/' . ATOM_BOARD . '/js/atomboard.js?2026051200"></script>
	<script src="/' . ATOM_BOARD .
		'/js/extension/Dollchan_Extension_Tools.user.js?2026051200" async defer></script>' .
	(ATOM_CAPTCHA === 'recaptcha' ? '
	<script src="https://www.google.com/recaptcha/api.js" async defer></script>' : '') . '
</head>
';
}

function pageWrapper(string $description, bool $needReturn): string {
	return '
	<nav id="navigation-top" class="navigation" aria-label="Top menu">' . ATOM_HTML_NAVIGATION . '
		<a class="navigation-link" href="/' . ATOM_BOARD . '/catalog.html" title="Go to catalog">Catalog</a>
		<a class="navigation-link" href="/' . ATOM_BOARD .
			'/' . basename($_SERVER['PHP_SELF']) . '?passcode">Passcode</a>
		<a class="navigation-link" href="/' . ATOM_BOARD .
			'/' . basename($_SERVER['PHP_SELF']) . '?manage">Manage</a>
		<select class="select-style navigation-link" onchange="setThemeStyle(this);">
			<option value="Dark">Dark</option>
			<option value="Light">Light</option>
		</select>
	</nav>
	<main class="wrapper">
		<h1 class="page-title">' . $description . '</h1>
		<hr>
		<div id="panel-top" class="panel">' .
			($needReturn ? '
			<a class="link-button" href="/' . ATOM_BOARD . '/" title="Return to board">Return</a>' : '') . '
			<button class="link-button" title="Navigate to bottom"' .
				' onclick="window.scroll(0, document.body.scrollHeight); return false;">To bottom</button>
		</div>
		';
}

function pageFooter(bool $needReturn): string {
	return '
		<div id="panel-bottom" class="panel">' .
			($needReturn ? '
			<a class="link-button" href="/' . ATOM_BOARD . '/" title="Return to board">Return</a>' : '') . '
			<button class="link-button" title="Navigate to top"' .
				' onclick="window.scroll(0, 0); return false;">To top</button>
		</div>
		<hr>
		<footer>
			<p>
				We are not responsible for the content posted on this site.
				Any information posted here is the responsibility of the user who uploaded it.<br>
				The content on the site is intended for persons over 18 years of age.
			</p>
			<p>- <a href="https://github.com/SthephanShinkufag/atomboard">atomboard</a> -</p>
		</footer>
	</main>
	<nav id="navigation-bottom" class="navigation" aria-label="Bottom menu"> ' . ATOM_HTML_NAVIGATION . '
		<a class="navigation-link" href="/' . ATOM_BOARD . '/catalog.html" title="Go to catalog">Catalog</a>
		<a class="navigation-link" href="/' . ATOM_BOARD .
			'/' . basename($_SERVER['PHP_SELF']) . '?passcode">Passcode</a>
		<a class="navigation-link" href="/' . ATOM_BOARD .
			'/' . basename($_SERVER['PHP_SELF']) . '?manage">Manage</a>
		<select class="select-style navigation-link" onchange="setThemeStyle(this);">
			<option value="Dark">Dark</option>
			<option value="Light">Light</option>
		</select>
	</nav>
	<div id="svg-icons" style="height: 0; width: 0; overflow: hidden;">
		<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
			<symbol viewBox="0 0 16 16" id="symbol-like">
				<path d="M14.8 1.6l-.3-.3C13-.5 10.4-.4 8.9 1.4l-.9 1-.9-1C5.6-.4 3-.4 1.5 1.4l-.3.3C-.4' .
					' 3.5-.4 6.3 1.1 8.1l1 1.1L8 16l5.9-6.8 1-1.2c1.5-1.8 1.5-4.6-.1-6.4z"/>
			</symbol>
		</svg>
	</div>
</body>
</html>';
}

/* ==[ Page ]============================================================================================== */

function buildPage(string $htmlPosts, int $parent, int $pages = 0, int $thisPage = 0): string {
	// Build page links: [Previous] [0] [1] [2] [Next]
	$pagelinks = '';
	$isInThread = $parent !== 0;
	if (!$isInThread) {
		$pages = max($pages, 0);
		$pagelinks = ($thisPage === 0 ?
			'<span class="pagelist-previous">[Previous]</span>' :
			'<span class="pagelist-previous">[<a href="' .
				($thisPage === 1 ? 'index' : $thisPage - 1) . '.html">Previous</a>]</span>') . '
			<span class="pagelist-links">';
		for ($i = 0; $i <= $pages; $i++) {
			$pagelinks .= $thisPage === $i ? '[' . $i . '] ' :
				'[<a href="' . ($i === 0 ? "index" : $i) . '.html">' . $i . '</a>] ';
		}
		$pagelinks .= '</span>' . ($pages <= $thisPage ? '
			<span class="pagelist-next">[Next]</span>' : '
			<span class="pagelist-next">[<a href="' . ($thisPage + 1) . '.html">Next</a>]</span>');
	}
	// Build page's body
	return pageHeader() . '<body class="tinyib atomboard de-runned">' .
		pageWrapper(ATOM_BOARD_DESCRIPTION, $isInThread) .
		(ATOM_HTML_INFO_TOP ? ATOM_HTML_INFO_TOP . '
		<hr>
		' : '') .
		buildPostForm($parent) . '
		<hr>
		<form id="delform" method="post" action="/' . ATOM_BOARD . '/imgboard.php?delete">
			<input type="hidden" name="board" value="' . ATOM_BOARD . '">' .
			$htmlPosts . '
			<menu class="userdelete">
				Delete Post <input type="password" name="password" id="deletepostpassword" size="8"' .
					' placeholder="Password">&nbsp;<input name="deletepost" value="Delete" type="submit">
			</menu>
		</form>
		<nav class="pagelist" aria-label="Pages">
			' . $pagelinks . '
		</nav>' .
		(ATOM_HTML_INFO_BOTTOM ? '
		<hr>
		' . ATOM_HTML_INFO_BOTTOM : '') .
		pageFooter($isInThread);
}

/* ==[ Rebuilding ]======================================================================================== */

function rebuildThreadPage(int $thrId): void {
	$htmlPosts = '
			<section class="thread" id="thread' . $thrId . '">';
	$posts = getThreadPosts($thrId);
	foreach ($posts as $post) {
		$htmlPosts .= buildPost($post, true);
	}
	$htmlPosts .= '
			</section>
			<hr>';
	writePage('res/' . $thrId . '.html', buildPage($htmlPosts, $thrId));
}

function rebuildIndexPages(): void {
	$page = 0;
	$i = 0;
	$htmlPosts = '';
	$threads = getThreads();
	$pages = (int)ceil(count($threads) / ATOM_THREADSPERPAGE) - 1;
	foreach ($threads as $thread) {
		$thrId = (int)$thread['id'];
		$replies = getThreadPosts($thrId);
		$thread['omitted'] = max(0, count($replies) - ATOM_PREVIEWREPLIES - 1);
		// Build replies for preview
		$htmlReplies = [];
		for ($j = count($replies) - 1; $j > $thread['omitted']; $j--) {
			$htmlReplies[] = buildPost($replies[$j]);
		}
		$htmlPosts .= '
			<section class="thread" id="thread' . $thrId . '">' .
				buildPost($thread) . implode('', array_reverse($htmlReplies)) . '
			</section>
			<hr>';
		if (++$i >= ATOM_THREADSPERPAGE) {
			$file = $page === 0 ? ATOM_INDEX : $page . '.html';
			writePage($file, buildPage($htmlPosts, 0, $pages, $page));
			$page++;
			$i = 0;
			$htmlPosts = '';
		}
	}
	if ($page === 0 || $htmlPosts !== '') {
		$file = $page === 0 ? ATOM_INDEX : $page . '.html';
		writePage($file, buildPage($htmlPosts, 0, $pages, $page));
	}
	// Create catalog
	writePage('catalog.html', makeCatalogPage());
}

function rebuildThread(int $thrId): void {
	rebuildThreadPage($thrId);
	rebuildIndexPages();
}

/* ==[ Catalog ]=========================================================================================== */

function makeCatalogPage(): string {
	$catalogHtml = '';
	$thumb = 'icons/noimage.png';
	$thumbWidth = ATOM_FILE_MAXW;
	$thumbHeight = ATOM_FILE_MAXH;
	$OPposts = getThreads();
	foreach ($OPposts as $post) {
		$postId = (int)$post['id'];
		$numOfReplies = getThreadPostsCount($postId);
		$message = tidy_repair_string(
			mb_substr($post['message'], 0, 160, 'UTF-8'),
			['quiet' => true, 'show-body-only' => true],
			'utf8');
		$subject = escapeHTML($post['subject']);
		$userName = escapeHTML($post['name'] ?: ATOM_POSTERNAME);
		if ($post['thumb0'] !== '' && $post['thumb0_width'] > 0 && $post['thumb0_height'] > 0) {
			$thumb = 'thumb/' . $post['thumb0'];
			$thumbWidth = $post['thumb0_width'];
			$thumbHeight = $post['thumb0_height'];
		} else {
			$thumb = 'icons/noimage.png';
			$thumbWidth = ATOM_FILE_MAXW;
			$thumbHeight = ATOM_FILE_MAXH;
		}
		$catalogHtml .= '
			<div class="catalog-block">
				<a href="res/' . $postId . '.html">
					<img src="' . $thumb . '" width="' . $thumbWidth . '" height="' . $thumbHeight . '" />
				</a>
				<br>
				<center>' .
					($subject ? '
					<span class="post-subject">' . $subject . '</span>
					<br>' : '') . '
					<span class="poster-name">' . $userName . '</span>
					<span>replies: ' . $numOfReplies . '</span>
					<br>
				</center>
				<blockquote class="post-message" style="text-align: left">' . $message . '</blockquote>
				<br>
			</div>';
	}
	return pageHeader() . '<body>' .
		pageWrapper(ATOM_BOARD_DESCRIPTION . ' / Catalog', true) .
		'<center>' .
			$catalogHtml . '
		</center>' .
		pageFooter(true);
}
