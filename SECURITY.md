# Security Policy

## Reporting Security Vulnerabilities

We take the security of LivroLog seriously. If you discover a security vulnerability in our personal library management system, please report it responsibly.

### How to Report

Please **do not** report security vulnerabilities through public GitHub issues, discussions, or pull requests.

Instead, please send an email to the project maintainers with the following information:

- **Description**: A clear description of the vulnerability
- **Steps to Reproduce**: Detailed steps to reproduce the issue
- **Impact Assessment**: Your assessment of the potential impact
- **Affected Components**: Which parts of the system are affected (API, frontend, database, etc.)
- **Suggested Fix**: Any potential solutions you might have identified (optional)

### What to Expect

- **Acknowledgment**: We will acknowledge receipt of your vulnerability report within 48 hours
- **Initial Assessment**: We will provide an initial assessment within 7 business days
- **Progress Updates**: We will keep you informed of our progress throughout the investigation
- **Resolution Timeline**: We aim to address critical vulnerabilities within 90 days

### Disclosure Policy

- We follow responsible disclosure practices
- We will work with you to understand and resolve the issue before any public disclosure
- We ask that you do not publicly disclose the vulnerability until we have had a chance to address it
- Once fixed, we will coordinate with you on the timing of any public disclosure

## Supported Versions

We provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| Latest  | :white_check_mark: |
| < Latest| :x:                |

Currently, we only support the latest version of LivroLog. Please ensure you are running the most recent version before reporting security issues.

## Security Features

LivroLog implements several security measures:

- **Authentication**: Laravel Sanctum with Bearer tokens
- **Authorization**: Role-based access control
- **Data Protection**: Encrypted sensitive data storage
- **API Security**: Rate limiting and input validation
- **Infrastructure**: Docker containerization for isolation

## Out of Scope

The following are generally considered out of scope for security reports:

- Issues requiring physical access to user devices
- Social engineering attacks
- Issues in third-party services (Google Books API, Google OAuth)
- Theoretical vulnerabilities without proof of concept
- Issues affecting unsupported or significantly outdated versions

## Recognition

We appreciate security researchers who help keep LivroLog and our users safe. With your permission, we will acknowledge your contribution in our release notes when we fix the reported vulnerability.

Thank you for helping keep LivroLog secure!