# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [3.0.3] - Unreleased

### Fixed
- Restore the Gravity Forms submit button classes (`button-atom`, `button-atom--primary`, `bde-button__button`) present in v2.3.0, while preserving native Gravity Forms classes.

## [3.0.2] - 2026-11-04

### Fixed
- Allow saved Inner Section padding settings to override the default padding supplied by Breakdance's native Section styles.

### Changed
- Show Inner Section and Wrapper Div together under the registered IcebergWebDesign category in Add Elements. Their saved element identities, class names, and layout remain unchanged.
- Regenerate Breakdance CSS cache after upgrading existing sites so previously generated defaults no longer override instance padding.

## [3.0.1] - 2026-11-03

### Fixed
- Scope Wrapper Div and Inner Section default CSS to their own elements so saving them cannot override native Breakdance Div/Section styles in other page templates.
- Keep the existing `bde-div` and `bde-section` classes for site CSS compatibility, while preventing Inner Section defaults from styling nested Sections.

### Upgrade note
- Regenerate Breakdance's CSS cache after updating an affected 3.0.0 site; previously generated stylesheets retain the old selectors until rebuilt.

## [3.0.0] - 2026-10-23

### Added
- WordPress update discovery from the Iceberg server and a signed package URL.
- Fresh package signature retrieval at install time to avoid expired cached URLs.
- Deterministic release ZIP tooling and standalone updater/package tests.

### Changed
- Declared minimum WordPress 6.0 and PHP 8.0; existing Element Studio save locations remain unchanged.

## [1.0.0] - 2023-10-12

Added new "Wrapper Div" element. This element is based on regular Div but can be added without Section as parent.
Added new "Inner Section" element. This element is based on regular Section but can be added as child or inside other elements unlike regular Section that can only be used as parent.

### Added

- New "Wrapper Div" element in Advanced
- New "Inner Section" element in Basic

### Changed

### Fixed
