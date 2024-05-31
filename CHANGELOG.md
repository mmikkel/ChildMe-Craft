# Child Me! Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.2.1 - 2024-05-31
### Fixed
- Fixed a misalignment issue with "New child" buttons in element index columns  

## 2.2.0 - 2024-05-18
### Changed 
- Setting the `EVENT_DEFINE_ENTRY_TYPES::$entryTypes` attribute to an empty array now results in the "New child" button not being rendered, instead of defaulting to the current entry's entry type.  

## 2.1.0 - 2024-05-09
### Fixed 
- Fixed a bug that prevented the "New child" button from appearing in certain situations
### Changed
- Replaced the "New child" tooltip for the "New child" buttons with a "New child" `title` attribute.  

## 2.0.0 - 2024-03-27
### Added
- Added Craft 5 compatibility
- Added support for entry type icons and colors in Child Me! entry type disclosure menus 
- Added the `entry` attribute to the `mmikkel\childme\events\DefineEntryTypesEvent` event class
### Improved
- Child Me! has been rewritten from scratch for Craft 5 🔥 
- Accessibility, performance and CX (child-creating experience) have all been improved 🎉
### Fixed
- Fixed an issue where Child Me! entry type menus could become visually cut off ([#14](https://github.com/mmikkel/ChildMe-Craft/issues/14))
