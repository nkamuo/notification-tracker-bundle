# Security Policy

## Supported Versions

We actively support the following versions with security updates:

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within NotificationTrackerBundle, please send an email to callistus@anvila.tech. All security vulnerabilities will be promptly addressed.

**Please do not report security vulnerabilities through public GitHub issues.**

When reporting a vulnerability, please include:

- A description of the vulnerability
- Steps to reproduce the issue
- Affected versions
- Any potential mitigations you've identified

We will acknowledge receipt of your vulnerability report and send you regular updates about our progress. If you haven't received a response within 48 hours, please follow up via email to ensure we received your original message.

## Security Measures

This bundle implements several security measures:

- **Webhook Signature Verification**: All webhook endpoints verify signatures from providers
- **IP Whitelisting**: Configurable IP restrictions for webhook endpoints
- **Input Validation**: All user inputs are validated and sanitized
- **SQL Injection Protection**: Uses Doctrine ORM with prepared statements
- **XSS Protection**: All output is properly escaped
- **CSRF Protection**: Follows Symfony security best practices

## Responsible Disclosure

We believe in responsible disclosure and will work with security researchers to address any vulnerabilities found in our code. We ask that you:

- Give us reasonable time to fix the issue before public disclosure
- Make a good faith effort to avoid privacy violations and data destruction
- Contact us before running automated scanners or performing extensive testing

Thank you for helping keep NotificationTrackerBundle and our users safe!
