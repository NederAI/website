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
- Keep implementations simple and transparent as described in `README.md`.
- Delegated routes are relative
