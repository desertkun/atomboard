<?php
declare(strict_types=1);

/* ==[ Posts ]============================================================================================= */

function newPost(int $parent): array {
	return [
		'parent' => $parent,
		'timestamp' => '0',
		'bumped' => '0',
		'ip' => '',
		'name' => '',
		'tripcode' => '',
		'email' => '',
		'nameblock' => '',
		'subject' => '',
		'message' => '',
		'password' => '',
		'file0' => '',
		'file0_hex' => '',
		'file0_original' => '',
		'file0_size' => '0',
		'file0_size_formatted' => '',
		'image0_width' => '0',
		'image0_height' => '0',
		'thumb0' => '',
		'thumb0_width' => '0',
		'thumb0_height' => '0',
		'file1' => '',
		'file1_hex' => '',
		'file1_original' => '',
		'file1_size' => '0',
		'file1_size_formatted' => '',
		'image1_width' => '0',
		'image1_height' => '0',
		'thumb1' => '',
		'thumb1_width' => '0',
		'thumb1_height' => '0',
		'file2' => '',
		'file2_hex' => '',
		'file2_original' => '',
		'file2_size' => '0',
		'file2_size_formatted' => '',
		'image2_width' => '0',
		'image2_height' => '0',
		'thumb2' => '',
		'thumb2_width' => '0',
		'thumb2_height' => '0',
		'file3' => '',
		'file3_hex' => '',
		'file3_original' => '',
		'file3_size' => '0',
		'file3_size_formatted' => '',
		'image3_width' => '0',
		'image3_height' => '0',
		'thumb3' => '',
		'thumb3_width' => '0',
		'thumb3_height' => '0',
		'likes' => '0',
		'moderated' => '1',
		'stickied' => '0',
		'locked' => '0',
		'endless' => '0'];
}

function isOp(array $post): bool {
	return (int)$post['parent'] === 0;
}

function deleteAllPosts(string $ip, ?int $parentId): string {
	$deletedPosts = '';
	$updThreads = [];
	$posts = getPostsByIP($ip);
	$count = 0;
	foreach ($posts as $post) {
		$id = (int)$post['id'];
		$thrId = (int)$post['parent'];
		if (!isset($parentId) || $thrId === $parentId) {
			deletePost($id);
			$deletedPosts .= ($count ? ', ' : '') . $id;
			if (!isOp($post) && !in_array($thrId, $updThreads)) {
				$updThreads[] = $thrId;
			}
			$count++;
		}
	}
	foreach ($updThreads as $updThreadId) {
		rebuildThreadPage($updThreadId);
	}
	modLog('Deleted all posts from IP ' . $ip . ': №' . $deletedPosts . '.');
	rebuildIndexPages();
	return $deletedPosts;
}

/* ==[ Threads ]=========================================================================================== */

function updateThreadPosts(int $thrId, array $post): void {
	if (isOp($post)) {
		return;
	}
	if (ATOM_THREAD_LIMIT === 0 || getThreadPostsCount($thrId) <= ATOM_THREAD_LIMIT) {
		if (strtolower($post['email']) !== 'sage') {
			bumpThread($thrId);
		}
	} elseif (ATOM_THREAD_LIMIT !== 0) {
		// Delete old posts in endless threads
		$postOP = getPost($thrId);
		if ($postOP && (int)$postOP['endless'] === 1) {
			$posts = getThreadPosts($thrId, false);
			$overLimit = count($posts) - ATOM_THREAD_LIMIT + 1;
			if ($overLimit > 0) {
				for ($i = 1; $i < $overLimit; $i++) {
					deletePost($posts[$i]['id']);
				}
			}
		}
	}
}

function getThreadId(array $post): int {
	return isOp($post) ? (int)$post['id'] : (int)$post['parent'];
}
