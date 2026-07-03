# Security Policy

## Supported Versions

The latest minor release receives security fixes.

## Reporting a Vulnerability

If you discover a security vulnerability, please email the maintainer privately
rather than opening a public issue. You will receive a response within a few
business days. Please do not disclose the issue publicly until a fix has been
released.

## Trusted Documentation Content

Laradocs renders Markdown, inline HTML, macros, Blade-style documentation
components, and OpenAPI descriptions from files stored in the consuming
application. Treat those files like application source code: only trusted
maintainers should be able to edit them.

By design, the Markdown pipeline allows authored HTML so application teams can
ship rich documentation without a sanitizer stripping valid examples or custom
markup. If documentation content is accepted from end users, a CMS, external
pull requests, translated files from an untrusted workflow, or a generated
OpenAPI specification that you do not control, sanitize or review that content
before it reaches Laradocs.

For private or internal documentation, also place the docs routes and optional
MCP endpoint behind your application's normal authentication middleware or MCP
auth guard.
