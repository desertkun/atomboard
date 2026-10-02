<?php
declare(strict_types=1);

/* ==[ Images/video files ]================================================================================ */

function deletePostImageFiles(array $post, array $imgList = []): void {
	if ($imgList && (count($imgList) <= ATOM_FILES_COUNT)) {
		foreach ($imgList as $arrayIndex => $index) {
			$index = (int)trim(basename($index));
			if (!isEmbed($post['file' . $index . '_hex']) && $post['file' . $index] !== '') {
				@unlink('src/' . $post['file' . $index]);
			}
			$thumbName = $post['thumb' . $index];
			if ($thumbName !== '' && $thumbName !== 'spoiler.png') {
				@unlink('thumb/' . $thumbName);
			}
		}
		return;
	}
	for ($index = 0; $index < ATOM_FILES_COUNT; $index++) {
		if (!isEmbed($post['file' . $index . '_hex']) && $post['file' . $index] !== '') {
			@unlink('src/' . $post['file' . $index]);
		}
		$thumbName = $post['thumb' . $index];
		if ($thumbName !== '' && $thumbName !== 'spoiler.png') {
			@unlink('thumb/' . $thumbName);
		}
	}
}

function deletePostThumbFiles(array $post, array $imgList): void {
	if ($imgList && (count($imgList) <= ATOM_FILES_COUNT)) {
		foreach ($imgList as $arrayIndex => $index) {
			$index = (int)trim(basename($index));
			$thumbName = $post['thumb' . $index];
			if ($thumbName !== '' && $thumbName !== 'spoiler.png') {
				@unlink('thumb/' . $thumbName);
			}
		}
	}
}

function getThumbnailDimensions(array $post, int $imgIdx = 0): array {
	if (isOp($post)) {
		$maxW = ATOM_FILE_MAXWOP;
		$maxH = ATOM_FILE_MAXHOP;
	} else {
		$maxW = ATOM_FILE_MAXW;
		$maxH = ATOM_FILE_MAXH;
	}
	return (
		$post['image' . $imgIdx . '_width'] > $maxW ||
		$post['image' . $imgIdx . '_height'] > $maxH
	) ? [$maxW, $maxH] :
		[$post['image' . $imgIdx . '_width'], $post['image' . $imgIdx . '_height']];
}

function createThumbnail(string $fileLocation, string $thumbLocation, int $newW, int $newH): bool {
	if (!file_exists($fileLocation)) {
		throw new Exception('Original file not found');
	}
	if (ATOM_FILE_THUMBDRIVER === 'imagick') {
		if (!extension_loaded('imagick')) {
			throw new Exception('The Imagick driver is not installed on the server');
		}
		try {
			$im = new Imagick($fileLocation);
			$format = $im->getImageFormat();
			// Check if the current Imagick can work with this format
			if (empty(Imagick::queryFormats($format))) {
				throw new Exception($format . ' format is not supported by your Imagick driver');
			}
			if ($im->getNumberImages() > 1) {
				// Multiple frames detected, working with animation (GIF, WebP, APNG)
				$im = $im->coalesceImages(); // Merge layers to remove artifacts
				if (!ATOM_FILE_ANIM_GIF) {
					// If animation is disabled, making a static first frame
					$static = new Imagick();
					$static->addImage($im->getImage());
					$im->destroy();
					$im = $static;
				} else {
					// If animation is allowed, resize each frame
					foreach ($im as $frame) {
						$frame->thumbnailImage($newW, $newH, true);
						$frame->setImagePage($newW, $newH, 0, 0);
					}
					$im->stripImage();
					return $im->writeImages($thumbLocation, true);
				}
			}
			// Working with statics (JPG, PNG or the first frame taken above)
			$im->thumbnailImage($newW, $newH, true);
			$im->stripImage(); // Remove EXIF data
			$im->setImageCompressionQuality(75); // Optimize quality for smaller file size
			return $im->writeImage($thumbLocation);
		} catch (Exception $e) {
			throw new Exception('Imagick error: ' . $e->getMessage());
		}
	} else if (ATOM_FILE_THUMBDRIVER === 'gd') {
		if (!extension_loaded('gd')) {
			throw new Exception("The GD driver is not installed on the server.");
		}
		$info = @getimagesize($fileLocation);
		if (!$info) {
			throw new Exception('Could not read image data (file corrupted?)');
		}
		// Create source image based on type
		$srcImg = match($info[2]) {
			IMAGETYPE_JPEG => imagecreatefromjpeg($fileLocation),
			IMAGETYPE_PNG  => imagecreatefrompng($fileLocation),
			IMAGETYPE_GIF  => imagecreatefromgif($fileLocation),
			IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($fileLocation) :
				throw new Exception('WebP format is not supported by your GD driver'),
			IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? imagecreatefromavif($fileLocation) :
				throw new Exception('AVIF format is not supported by your GD driver'),
			default => throw new Exception(($info['mime'] ?? 'unknown') .
				' format is not supported by your GD driver'),
		};
		if (!$srcImg) {
			throw new Exception(($info['mime'] ?? 'unknown') . ' format is not supported by your GD driver');
		}
		// Calculating proportions
		$oldX = imagesx($srcImg);
		$oldY = imagesy($srcImg);
		$scale = min($newW / $oldX, $newH / $oldY);
		$thumbW = (int)max(1, $oldX * $scale);
		$thumbH = (int)max(1, $oldY * $scale);
		// Creating new true color image
		$dstImg = imagecreatetruecolor($thumbW, $thumbH);
		// Handle transparency (PNG, WebP, AVIF)
		imagealphablending($dstImg, false);
		// Enable saving alpha channel
		imagesavealpha($dstImg, true);
		// Fill with transparent color
		imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $thumbW, $thumbH, $oldX, $oldY);
		// Saving thumbnail with appropriate function based on extension
		$extension = strtolower(pathinfo($thumbLocation, PATHINFO_EXTENSION));
		$result = match($extension) {
			'jpg', 'jpeg' => imagejpeg($dstImg, $thumbLocation, 80),
			'png'         => imagepng($dstImg, $thumbLocation),
			'gif'         => imagegif($dstImg, $thumbLocation),
			'webp'        => imagewebp($dstImg, $thumbLocation, 80),
			'avif'        => imageavif($dstImg, $thumbLocation, 80),
			default       => false,
		};
		imagedestroy($dstImg);
		imagedestroy($srcImg);
		if (!$result) {
			throw new Exception('The GD driver failed to save the output file to ' . $thumbLocation);
		}
		return true;
	}
	throw new Exception('An unknown ATOM_FILE_THUMBDRIVER has been selected: "' .
		ATOM_FILE_THUMBDRIVER . '"');
}

function isEmbed(string $fileHex): bool {
	global $atom_embeds;
	return in_array($fileHex, array_keys($atom_embeds));
}

function getEmbed(string $url): array {
	global $atom_embeds;
	if (sizeof($atom_embeds) !== 0) {
		foreach ($atom_embeds as $service => $service_url) {
			if (strpos(strtolower($url), strtolower($service)) !== false) {
				$service_url = str_ireplace('ATOM_EMBED', urlencode($url), $service_url);
				$result = json_decode(url_get_contents($service_url), true);
				if (!empty($result)) {
					return [$service, $result];
				}
			}
		}
	}
	return ['', []];
}

/* ==[ File reading/writing ]============================================================================== */

function url_get_contents(string $url, $use_include_path = false, $context = null): string|false {
	// Extract the timeout from the context if provided (for cURL)
	$timeout = 7; // Default value
	if ($context) {
		$options = stream_context_get_options($context);
		if (isset($options['http']['timeout'])) {
			$timeout = $options['http']['timeout'];
		}
	}

	// If cURL is not installed, use the standard file_get_contents
	if (!function_exists('curl_init')) {
		// If context is not provided, create it locally with a timeout
		if (!$context) {
			$context = stream_context_create(['http' => ['timeout' => $timeout]]);
		}
		return file_get_contents($url, $use_include_path, $context);
	}

	// If cURL is available, use it with timeout support
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout); // Timeout for connection
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Timeout for receiving data
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}

function writePage(string $filename, string $contents): void {
	$tempfile = tempnam('res/', ATOM_BOARD . 'tmp'); // Create a temporary file
	$fp = fopen($tempfile, 'w');
	fwrite($fp, $contents);
	fclose($fp);
	// If not able to use the rename function, try the alternate method
	if (!@rename($tempfile, $filename)) {
		copy($tempfile, $filename);
		unlink($tempfile);
	}
	chmod($filename, 0664);
}
