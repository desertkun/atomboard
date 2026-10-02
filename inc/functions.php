<?php
declare(strict_types=1);

/* ==[ Strings ]=========================================================================================== */

function escapeHTML(string $string): string {
	return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function plural(string $singular, int $count, string $plural = 's'): string {
	if ($plural === 's') {
		$plural = $singular . $plural;
	}
	return $count === 1 ? $singular : $plural;
}

function strallpos(string $haystack, string $needle, int $offset = 0): array {
	$result = [];
	for ($i = $offset; $i < strlen($haystack); $i++) {
		$pos = strpos($haystack, $needle, $i);
		if ($pos !== False) {
			$offset = $pos;
			if ($offset >= $i) {
				$i = $offset;
				$result[] = $offset;
			}
		}
	}
	return $result;
}

function hslToHex(float $h, float $s, float $l): string {
	$h /= 360;
	$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
	$p = 2 * $l - $q;
	$f = function(float $t) use ($p, $q): float {
		if ($t < 0) $t += 1;
		if ($t > 1) $t -= 1;
		if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
		if ($t < 1/2) return $q;
		if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
		return $p;
	};
	return sprintf('#%02x%02x%02x', 
		(int)round($f($h + 1/3) * 255), 
		(int)round($f($h) * 255), 
		(int)round($f($h - 1/3) * 255));
}

require_once __DIR__ . '/posts.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/access.php';
