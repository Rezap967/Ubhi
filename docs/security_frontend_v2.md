# Security Guide: Frontend & Interaction Layer

This document outlines the security requirements and best practices for the frontend layer of an application, specifically focusing on its interaction with the Laravel backend.

## 1. Cross-Site Scripting (XSS) Prevention
The primary goal is to prevent the injection of malicious scripts.
* **Blade Templating:** Always use `{{ $variable }}` for outputting data. This automatically applies `htmlspecialchars()` and prevents script execution.
* **Prohibited Practice:** Strictly avoid `{!! $variable !!}` unless the data is trusted/sanitized (e.g., from an internal rich-text processor).
* **DOM Security:** When using JavaScript (Vue/React) to manipulate the DOM, avoid setting `innerHTML`. Use `textContent` or framework-native data binding. Always use a library like `DOMPurify` if you must render user-provided HTML.

## 2. Cross-Site Request Forgery (CSRF) Protection
All state-changing requests (POST, PUT, PATCH, DELETE) must be authenticated by a CSRF token.
* **Blade Forms:** Always include `@csrf` inside your `<form>` tags.
* **JavaScript/AJAX:** Ensure the `X-CSRF-TOKEN` header is present in every request. 
    * *Note:* Laravel Breeze automatically configures this in `resources/js/bootstrap.js`. Verify this configuration exists.

## 3. Secure Data Handling
* **API Resources:** Use Laravel API Resources to strictly define which fields are sent to the frontend.
* **Filter Sensitive Info:** Never pass database objects directly to the frontend. Exclude `password`, `api_token`, `remember_token`, or any internal-use fields before returning JSON responses.
* **Input Validation:** Frontend validation (e.g., `required`, `email` attributes in HTML) is for **User Experience only**. All input **must** be re-validated in the Laravel Controller.
* **Storage Security:** Avoid storing sensitive tokens in `localStorage` or `sessionStorage` where they are vulnerable to XSS. Prefer `HttpOnly` cookies.

## 4. Session, Cookies, and Headers
Configure `config/session.php` and security headers:
* **HttpOnly & Secure:** Ensure these flags are enabled for cookies to prevent JS access and ensure HTTPS-only transmission.
* **SameSite:** Set to `lax` or `strict` to prevent CSRF.
* **Clickjacking Protection:** Laravel’s `FrameGuard` middleware automatically handles `X-Frame-Options: SAMEORIGIN`. Ensure it is active.
* **Referrer Policy:** Use `<meta name="referrer" content="strict-origin-when-cross-origin">` to limit information leakage when navigating to external sites.

## 5. Subresource Integrity (SRI)
* When loading assets (JS/CSS) from external CDNs, use the `integrity` attribute.
    * Example: `<script src="..." integrity="sha384-..." crossorigin="anonymous"></script>`
* This ensures that if the CDN is compromised, the browser will refuse to load altered, malicious files.

## 6. Security Principles for the AI
* **Trust Nothing:** Treat any input provided via the browser as potentially malicious.
* **Layered Defense:** Security is a two-sided operation. Frontend provides the first layer of validation/UX, but the Backend is the final point of truth.
* **Context Matters:** When reviewing code, distinguish between user-generated content (danger) and system-generated content (safe).
