# Changelog

All notable changes to this plugin will be documented in this file.

The format is based on Keep a Changelog.

## [Unreleased]

### Added
- _No changes yet._

## [0.1.10]

### Fixed
- In `/otqr/upload/?k=CLAVE`, the **Gestionar** button now points to `/otqr/manage/` without exposing the public key.

## [0.1.9]

### Changed
- `/otqr/manage/` stopped using `k` in internal links (edit, pagination and related navigation).
- Manager actions now use a dedicated nonce action: `otqr_manage_action`.
- Manager internal links were aligned so the protected flow does not propagate public key parameters.

### Security
- Manager login redirect was hardened to avoid relying on raw request URI input.

## [0.1.8]

### Security
- `/otqr/manage/` was restricted to authenticated users with `current_user_can('manage_options')`.

## [0.1.7]

### Added
- Public key-based upload flow at `/otqr/upload/?k=CLAVE`.
- Key-based management endpoint and admin page to view/update the key.
- Frontend upload flow for PDF-only submissions.
- OT cover generation route `/ot/{NUM}/cover/`.

### Changed
- Filename parsing enforces OT/modelo/cliente extraction from PDF filename format.
