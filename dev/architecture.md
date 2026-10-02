# Engine layout

`imgboard.php` initializes the HTTP request and dispatches it. Its request
handlers preserve the existing URLs, response bodies, redirects, and request
order. New behavior should enter through a small handler and delegate work to
functions with explicit inputs.

| Area | Files |
| --- | --- |
| Schema and database | `inc/schema.php`, `inc/database_common.php`, `inc/database_pdo.php`, `inc/database_mysqli.php` |
| Posting and uploads | `inc/posting.php`, `inc/media.php` |
| Management | `inc/management.php` |
| Shared operations | `inc/functions.php`, `inc/posts.php`, `inc/storage.php`, `inc/access.php` |
| Pages and forms | `inc/html.php`, `inc/html_post.php`, `inc/html_management.php`, `inc/html_bans.php`, `inc/html_passcodes.php`, `inc/html_moderation.php` |
| CAPTCHA | `inc/captcha.php` |

`inc/functions.php` loads the posts, storage, and access helpers so existing
callers continue to use their global function names. The entry point loads
schema definitions before the selected database driver. Each driver loads
`database_common.php` after defining its own `dbWrite()` adapter. Only one
driver is loaded in a request. `dev/bootstrap.php` and `dev/seed.php` mirror
the relevant include order.

The engine still relies on settings constants, PHP superglobals, and global
database handles. Generated HTML pages and uploaded files are also part of a
request's result. Keep these boundary effects visible when changing a flow:
test its HTTP response, database row, generated page, and files where relevant.

For source edits, use the repository's tab indentation for PHP, add
`declare(strict_types=1)` to new PHP files, and run `make lint`. Keep a style
change in its own commit when it would obscure behavior changes. Run the PDO,
mysqli, PostgreSQL, and JavaScript test targets before merging
changes to shared behavior.

The bundled Dollchan extension, reCAPTCHA library, and username lists are
separate from the engine refactor. The current test suite does not cover
PostgreSQL HTTP requests, real browser behavior of the extension, external IP
services, or every premoderation setting. Add characterization tests before
changing those paths.
