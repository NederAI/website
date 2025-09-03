# Agent Guidelines

This repository uses the **Delegating App Base** (DAB) framework.
Follow these conventions when modifying or extending the code:

- `public/index.php` loads `App\Controllers\FrontController` as the entry point.
- `FrontController` delegates requests based on URL prefixes:
  - `/assets` -> `AssetsController`
  - `/api` -> `ApiController`
  - everything else -> `HtmlController`
- **Always implement new API routes inside `ApiController` or a controller routed from it.**
- **Serve new HTML pages from `HtmlController` or a controller routed from it.**
- Controllers extend `Core\BaseController` and route subpaths via `delegateRoute()` in their `handle()` method.
- Use the classes in the `core/` directory for shared functionality:
  - Resolve dependencies through `Core\Container` instead of manual instantiation.
  - Get database access via `Core\Database` and file operations via `Core\File`.
  - Report recoverable problems by throwing `Core\Error`; let the provided error handlers respond.
- Keep implementations simple and transparent as described in `README.md`.
- Delegated routes are relative
