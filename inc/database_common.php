<?php
declare(strict_types=1);

function postInsertStatement(array $post, int $now, string $ip): array {
	return [
		"INSERT INTO " . ATOM_DBPOSTS . " (
			parent,
			timestamp,
			bumped,
			ip,
			name,
			tripcode,
			email,
			nameblock,
			subject,
			message,
			password,
			file0,
			file0_hex,
			file0_original,
			file0_size,
			file0_size_formatted,
			image0_width,
			image0_height,
			thumb0,
			thumb0_width,
			thumb0_height,
			file1,
			file1_hex,
			file1_original,
			file1_size,
			file1_size_formatted,
			image1_width,
			image1_height,
			thumb1,
			thumb1_width,
			thumb1_height,
			file2,
			file2_hex,
			file2_original,
			file2_size,
			file2_size_formatted,
			image2_width,
			image2_height,
			thumb2,
			thumb2_width,
			thumb2_height,
			file3,
			file3_hex,
			file3_original,
			file3_size,
			file3_size_formatted,
			image3_width,
			image3_height,
			thumb3,
			thumb3_width,
			thumb3_height,
			likes,
			moderated,
			stickied,
			locked,
			endless,
			pass
		) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
		[
			$post['parent'],
			$now,
			$now,
			$ip,
			$post['name'],
			$post['tripcode'],
			$post['email'],
			$post['nameblock'],
			$post['subject'],
			$post['message'],
			$post['password'],
			$post['file0'],
			$post['file0_hex'],
			$post['file0_original'],
			$post['file0_size'],
			$post['file0_size_formatted'],
			$post['image0_width'],
			$post['image0_height'],
			$post['thumb0'],
			$post['thumb0_width'],
			$post['thumb0_height'],
			$post['file1'],
			$post['file1_hex'],
			$post['file1_original'],
			$post['file1_size'],
			$post['file1_size_formatted'],
			$post['image1_width'],
			$post['image1_height'],
			$post['thumb1'],
			$post['thumb1_width'],
			$post['thumb1_height'],
			$post['file2'],
			$post['file2_hex'],
			$post['file2_original'],
			$post['file2_size'],
			$post['file2_size_formatted'],
			$post['image2_width'],
			$post['image2_height'],
			$post['thumb2'],
			$post['thumb2_width'],
			$post['thumb2_height'],
			$post['file3'],
			$post['file3_hex'],
			$post['file3_original'],
			$post['file3_size'],
			$post['file3_size_formatted'],
			$post['image3_width'],
			$post['image3_height'],
			$post['thumb3'],
			$post['thumb3_width'],
			$post['thumb3_height'],
			$post['likes'],
			$post['moderated'],
			$post['stickied'],
			$post['locked'],
			$post['endless'],
			$post['pass']
		]
	];
}

function approvePost(int $id): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET moderated = ?
		WHERE id = ?",
		['1', $id]);
}

function deletePost(int $id): void {
	$posts = getThreadPosts($id, false);
	foreach ($posts as $post) {
		$postId = (int)$post['id'];
		if ($postId !== $id) {
			deletePostImageFiles($post);
			dbWrite(
				"DELETE FROM " . ATOM_DBPOSTS . "
				WHERE id = ?",
				[$postId]);
		} else {
			$thispost = $post;
		}
	}
	if (isset($thispost)) {
		$thispostId = (int)$thispost['id'];
		if ($thispost['parent'] === 0) {
			@unlink('res/' . $thispostId . '.html');
		}
		deletePostImageFiles($thispost);
		dbWrite(
			"DELETE FROM " . ATOM_DBPOSTS . "
			WHERE id = ?",
			[$thispostId]);
	}
	deleteReports($id);
	deleteLikes($id);
}

function deletePostImages(array $post, array $imgList): void {
	deletePostImageFiles($post, $imgList);
	if ($imgList && count($imgList) <= ATOM_FILES_COUNT) {
		foreach ($imgList as $arrayIndex => $index) {
			$index = intval(trim(basename($index)));
			dbWrite(
				"UPDATE " . ATOM_DBPOSTS . "
				SET file" . $index . " = ?,
					file" . $index . "_hex = ?,
					file" . $index . "_original = ?,
					file" . $index . "_size = ?,
					file" . $index . "_size_formatted = ?,
					image" . $index . "_width = ?,
					image" . $index . "_height = ?,
					thumb" . $index . " = ?,
					thumb" . $index . "_width = ?,
					thumb" . $index . "_height = ?
				WHERE id = ?",
				['', '', '', '0', '', '0', '0', '', '0', '0', $post['id']]);
		}
	}
}

function hidePostImages(array $post, array $imgList): void {
	deletePostThumbFiles($post, $imgList);
	if ($imgList && (count($imgList) <= ATOM_FILES_COUNT) ) {
		foreach ($imgList as $arrayIndex => $index) {
			$index = intval(trim(basename($index)));
			dbWrite(
				"UPDATE " . ATOM_DBPOSTS . "
				SET thumb" . $index . " = ?,
					thumb" . $index . "_width = ?,
					thumb" . $index . "_height = ?
				WHERE id = ?",
				['spoiler.png', ATOM_FILE_MAXW, ATOM_FILE_MAXW, $post['id']]);
		}
	}
}

function editPostMessage(int $id, string $newMessage): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET message = ?
		WHERE id = ?",
		[$newMessage, $id]);
}

function toggleStickyThread(int $id, int $isStickied): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET stickied = ?
		WHERE id = ?",
		[$isStickied, $id]);
}

function toggleLockThread(int $id, int $isLocked): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET locked = ?
		WHERE id = ?",
		[$isLocked, $id]);
}

function toggleEndlessThread(int $id, int $isEndless): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET endless = ?
		WHERE id = ?",
		[$isEndless, $id]);
}

function bumpThread(int $id): void {
	dbWrite(
		"UPDATE " . ATOM_DBPOSTS . "
		SET bumped = ?
		WHERE id = ?",
		[time(), $id]);
}
