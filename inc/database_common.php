<?php
declare(strict_types=1);

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
