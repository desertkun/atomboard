<?php
declare(strict_types=1);

function attachPostMedia(
	array &$post, array $hideFields, bool $isStaffPost, bool $isPasscode, array $atom_embeds, array $atom_uploads
): void {
	if (isset($_POST['embed']) &&
		trim($_POST['embed']) !== '' &&
		($isStaffPost || !in_array('embed', $hideFields))
	) {
		attachEmbed($post, $atom_embeds);
	} elseif (isset($_FILES['file']) && $_FILES['file']['name'][0] !== '' &&
		($isStaffPost || !in_array('file', $hideFields))
	) {
		attachUploadedFiles($post, $isPasscode, $atom_uploads);
	}
}

function attachEmbed(array &$post, array $atom_embeds): void {
	if (isset($_FILES['file']) && $_FILES['file']['name'][0] !== '') {
		fancyDie('Posting error: Adding a file and embed URL at the same time is not supported.');
	}
	[$service, $embed] = getEmbed(trim($_POST['embed']));
	if (empty($embed) || !isset($embed['html'], $embed['title'], $embed['thumbnail_url'])) {
		fancyDie('Posting error: Invalid embed URL.<br>Only ' .
			(implode(' / ', array_keys($atom_embeds))) . ' URLs are supported.');
	}
	$post['file0_hex'] = $service;
	$fileName = time() . substr(microtime(), 2, 3) . '-0';
	$fileLocation = 'thumb/' . $fileName;
	file_put_contents($fileLocation, url_get_contents($embed['thumbnail_url']));
	$fileInfo = getimagesize($fileLocation);
	$post['image0_width'] = $fileInfo[0];
	$post['image0_height'] = $fileInfo[1];
	switch(mime_content_type($fileLocation)) {
	case 'image/avif': $post['thumb0'] = $fileName . '.avif'; break;
	case 'image/gif': $post['thumb0'] = $fileName . '.gif'; break;
	case 'image/jpeg': $post['thumb0'] = $fileName . '.jpg'; break;
	case 'image/png': $post['thumb0'] = $fileName . '.png'; break;
	case 'image/webp': $post['thumb0'] = $fileName . '.webp'; break;
	default: fancyDie('Posting error: Unsupported embed URL file type.');
	}
	$thumbLocation = 'thumb/' . $post['thumb0'];
	[$thumbMaxW, $thumbMaxH] = getThumbnailDimensions($post, 0);
	try {
		if (!createThumbnail($fileLocation, $thumbLocation, (int)$thumbMaxW, (int)$thumbMaxH)) {
			throw new Exception('Posting error: Error while creating thumbnail for the embed URL.');
		}
	} catch (Exception $e) {
		@unlink($fileLocation);
		fancyDie('Posting error: ' . $e->getMessage() . ' (for the embed URL).');
	}
	@unlink($fileLocation);
	$thumbInfo = getimagesize($thumbLocation);
	$post['thumb0_width'] = $thumbInfo[0];
	$post['thumb0_height'] = $thumbInfo[1];
	$post['file0_original'] = escapeHTML($embed['title']);
	$embedHtml = $embed['html'];
	if ($service === 'YouTube.com') {
		$embedHtml = preg_replace('/width="\d+"/', 'width="' . $fileInfo[0] . '"', $embedHtml);
		$embedHtml = preg_replace('/height="\d+"/', 'height="' . $fileInfo[1] . '"', $embedHtml);
	}
	$post['file0'] = str_ireplace(['src="https://', 'src="http://'], 'src="//', $embedHtml);
}

function attachUploadedFiles(array &$post, bool $isPasscode, array $atom_uploads): void {
	$fileIdx = 0;
	$filesCount = 0;
	foreach ($_FILES['file']['error'] as $index => $error) {
		$fileIdx++;
		if ($filesCount >= ATOM_FILES_COUNT || $fileIdx > 1 && $error === UPLOAD_ERR_NO_FILE) {
			continue;
		}

		$fileIdxTxt = 'File №' . $fileIdx;
		$fileSizeErrorText = $fileIdxTxt . ' is larger than ' . ATOM_FILE_MAXKBDESC .
			(ATOM_PASSCODES_ENABLED ? ' (' . ATOM_FILE_MAXKBDESC_PASS . ' for passcode users).' : '.');

		// Check for upload errors
		switch ($error) {
		case UPLOAD_ERR_OK: break;
		case UPLOAD_ERR_FORM_SIZE:
			fancyDie('Posting error: ' . $fileSizeErrorText);
			break;
		case UPLOAD_ERR_INI_SIZE:
			fancyDie('Posting error: ' . $fileIdxTxt . ' exceeds the upload_max_filesize directive (' .
				ini_get('upload_max_filesize') . ').');
			break;
		case UPLOAD_ERR_PARTIAL:
			fancyDie('Posting error: ' . $fileIdxTxt . ' was only partially uploaded.');
			break;
		case UPLOAD_ERR_NO_FILE:
			fancyDie('Posting error: No file was uploaded for ' . $fileIdxTxt . '.');
			break;
		case UPLOAD_ERR_NO_TMP_DIR:
			fancyDie('Posting error: Missing a temporary folder for ' . $fileIdxTxt . '.');
			break;
		case UPLOAD_ERR_CANT_WRITE:
			fancyDie('Posting error: Failed to write ' . $fileIdxTxt . ' to disk.');
			break;
		case UPLOAD_ERR_EXTENSION:
			fancyDie('Posting error: Upload stopped by PHP extension for ' . $fileIdxTxt . '.');
			break;
		default: fancyDie('Posting error: Unknown upload error for ' . $fileIdxTxt . '.');
		}
		$file = $_FILES['file']['tmp_name'][$index];
		if (!is_file($file) || !is_readable($file)) {
			fancyDie('Posting error: File transfer failure for ' . $fileIdxTxt . '.');
		}

		// Check for bytes size restriction
		if (ATOM_PASSCODES_ENABLED && $isPasscode) {
			if (ATOM_FILE_MAXKB_PASS > 0 && filesize($file) > ATOM_FILE_MAXKB_PASS * 1024) {
				fancyDie('Posting error: ' . $fileSizeErrorText);
			}
		} else if (ATOM_FILE_MAXKB > 0 && filesize($file) > ATOM_FILE_MAXKB * 1024) {
			fancyDie('Posting error: ' . $fileSizeErrorText);
		}

		// Get post image fields
		$filePath = pathinfo($_FILES['file']['name'][$index]);
		$post['file' . $index . '_original'] =
			trim(htmlentities(mb_substr($filePath['filename'], 0, 200) .
			'.' . $filePath['extension'], ENT_QUOTES, 'UTF-8'));
		$post['file' . $index . '_hex'] = md5_file($file);
		$sizeInBytes = (int)$_FILES['file']['size'][$index];
		$post['file' . $index . '_size'] = $sizeInBytes;

		// Convert file bytes size to a human-readable format
		if ($sizeInBytes < 1024) {
			$formattedSize = $sizeInBytes . "B";
		} elseif ($sizeInBytes < 1048576) {
			$formattedSize = sprintf("%0.2fKB", $sizeInBytes / 1024);
		} elseif ($sizeInBytes < 1073741824) {
			$formattedSize = sprintf("%0.2fMB", $sizeInBytes / 1048576);
		} else {
			$formattedSize = sprintf("%0.2fGB", $sizeInBytes / 1073741824);
		}
		$post['file' . $index . '_size_formatted'] = $formattedSize;

		// Check for file duplicates
		if (ATOM_FILE_DUPLICATE === false) {
			$hex = $post['file' . $index . '_hex'];
			$hexMatch = getPostsByImageHex($hex);
			if ($hexMatch) {
				fancyDie('Posting error: Duplicate ' . $fileIdxTxt .
					' uploaded.<br>That file has already been posted <a href="res/' .
					getThreadId($hexMatch) . '.html#' . $hexMatch['id'] . '">here</a>.');
			}
		}

		// Check for supported file types
		$fileMimeSplit = explode(' ', trim(mime_content_type($file)));
		if (count($fileMimeSplit) > 0) {
			$fileMime = strtolower(array_pop($fileMimeSplit));
		} else {
			if (!@getimagesize($file)) {
				fancyDie('Posting error: Failed to read the MIME type and size of the uploaded ' .
					$fileIdxTxt . '.');
			}
			$fileMime = mime_content_type($file);
		}
		if (empty($fileMime) || !isset($atom_uploads[$fileMime])) {
			fancyDie('Posting error: Unsupported file type for ' . $fileIdxTxt . ' ('. $fileMime .
				').<br>' . supportedFileTypes());
		}

		// Generate file name and location
		$fileName = time() . substr(microtime(), 2, 3) . '-' . $index;
		$post['file' . $index] = $fileName . '.' . $atom_uploads[$fileMime][0];
		$fileLocation = 'src/' . $post['file' . $index];

		// Upload file
		if (!move_uploaded_file($file, $fileLocation)) {
			fancyDie('Posting error: Could not copy uploaded ' . $fileIdxTxt . '.');
		}
		if ((int)$_FILES['file']['size'][$index] !== filesize($fileLocation)) {
			@unlink($fileLocation);
			fancyDie('Posting error: File transfer failure for ' . $fileIdxTxt . '.');
		}

		// Get video info and its thumbnail
		$thumbIdx = 'thumb' . $index;
		if (in_array($fileMime, ['audio/webm', 'video/webm', 'video/mp4', 'video/quicktime'])) {
			// Get all data (W, H, Duration) with one call to mediainfo
			// Use the | separator to parse the string.
			$infoRaw = shell_exec("mediainfo --Inform='Video;%Width%|%Height%|%Duration%' " .
				escapeshellarg($fileLocation));
			if (!is_string($infoRaw) || trim($infoRaw) === '') {
				@unlink($fileLocation);
				fancyDie('Posting error: Could not inspect video ' . $fileIdxTxt . '.');
			}
			$infoData = explode('|', trim($infoRaw));
			$videoWidth  = (int)($infoData[0] ?? 0);
			$videoHeight = (int)($infoData[1] ?? 0);
			$durationMs  = (int)($infoData[2] ?? 0);
			if ($videoWidth <= 0 || $videoHeight <= 0 || $videoWidth > 32766 || $videoHeight > 32766) {
				@unlink($fileLocation);
				fancyDie('Posting error: Video ' . $fileIdxTxt . ' appears to be corrupt or too large.');
			}
			$post['image' . $index . '_width']  = $videoWidth;
			$post['image' . $index . '_height'] = $videoHeight;

			// Thumnail generation with ffmpegthumbnailer
			[$thumbMaxW, $thumbMaxH] = getThumbnailDimensions($post, $index);
			$post[$thumbIdx] = $fileName . 's.jpg';
			$thumbPath = 'thumb/' . $post[$thumbIdx];
			$size = max($thumbMaxW, $thumbMaxH);
			// -t 10% to capture a frame from the middle of the video
			shell_exec('ffmpegthumbnailer -i ' . escapeshellarg($fileLocation) . ' -o ' .
				escapeshellarg($thumbPath) . ' -s ' . $size . ' -t 10%');
			if (!file_exists($thumbPath)) {
				@unlink($fileLocation);
				fancyDie('Posting error: Failed to create the thumbnail for ' . $fileIdxTxt . '.');
			}
			$thumbInfo = @getimagesize($thumbPath);
			if (!$thumbInfo) {
				@unlink($fileLocation);
				@unlink($thumbPath);
				fancyDie('Posting error: Failed to read the thumbnail for ' . $fileIdxTxt . '.');
			}
			$post[$thumbIdx . '_width']  = $thumbInfo[0];
			$post[$thumbIdx . '_height'] = $thumbInfo[1];

			// Formatting Duration
			if ($durationMs > 0) {
				$totalSecs = floor($durationMs / 1000);
				$mins = floor($totalSecs / 60);
				$secs = str_pad((string)($totalSecs % 60), 2, '0', STR_PAD_LEFT);
				$post['file' . $index . '_size_formatted'] = $mins . ':' . $secs . ', ' .
					$post['file' . $index . '_size_formatted'];
			}
		}

		// Get image info
		elseif (in_array($fileMime, [
			'image/avif',
			'image/gif',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/webp'
		])) {
			$fileInfo = @getimagesize($fileLocation);
			$post['image' . $index . '_width'] = $fileInfo[0];
			$post['image' . $index . '_height'] = $fileInfo[1];
		}

		// Get optional image thumbnail
		if (isset($atom_uploads[$fileMime][1])) {
			$thumbFileSplit = explode('.', $atom_uploads[$fileMime][1]);
			$post[$thumbIdx] = $fileName . 's.' . array_pop($thumbFileSplit);
			if (!copy($atom_uploads[$fileMime][1], 'thumb/' . $post[$thumbIdx])) {
				@unlink($fileLocation);
				fancyDie('Posting error: Could not create a thumbnail for ' . $fileIdxTxt . '.');
			}
		}

		// Get default image thumbnail
		elseif (in_array($fileMime, [
			'image/avif',
			'image/gif',
			'image/heif',
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/webp'
		])) {
			$post[$thumbIdx] = $fileName . 's.' . $atom_uploads[$fileMime][0];
			[$thumbMaxW, $thumbMaxH] = getThumbnailDimensions($post, $index);
			try {
				if (!createThumbnail($fileLocation, 'thumb/' . $post[$thumbIdx],
					(int)$thumbMaxW, (int)$thumbMaxH)
				) {
					throw new Exception('Posting error: Error while creating thumbnail for ' .
						$fileIdxTxt . '.');
				}
			} catch (Exception $e) {
				@unlink($fileLocation);
				fancyDie('Posting error: ' . $e->getMessage() . ' (for ' . $fileIdxTxt . ').');
			}
		}

		// Get thumbnail info
		if ($post[$thumbIdx] !== '') {
			$thumbInfo = @getimagesize('thumb/' . $post[$thumbIdx]);
			$post[$thumbIdx . '_width'] = $thumbInfo[0];
			$post[$thumbIdx . '_height'] = $thumbInfo[1];
		}

		$filesCount++;
	}
}
