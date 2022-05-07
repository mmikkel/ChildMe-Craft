# Child Me! Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 1.3.1 - 2022-05-07
### Changed
- Child Me! now defers any element queries to the `craft\web\Application::EVENT_INIT` event, avoiding potential issues with element queries being executed before Craft has fully initialised.

## 1.3.0 - 2022-04-05

### Added
- Added Craft 4.0 compatibility 

### Fixed
- Fixes an issue where Child Me! would trigger the `EVENT_DEFINE_ENTRY_TYPES` event for all sections, not just structures

### Improved
- Child Me!'s CSS and JS assets no longer outputs for pages rendered in control panel requests, if the template that rendered was in the site template folder.

### Changed
- Changed plugin icon
- Child Me! now requires Craft 3.7.0+

## 1.2.0 - 2020-11-21  

### Added  
- Added a new `EVENT_DEFINE_ENTRY_TYPES` event, giving modules and plugins a chance to modify the available entry types in Child Me!'s entry type menu  

### Changed  
- The "Add child" button in entry indexes no longer defaults to each entry's own type, but the first available entry type for the entry's section  

## 1.1.2 - 2020-09-15  
### Fixed     
- Fixes an issue where Child Me! could fail to select the correct site on multisite installs, upon creating new categories or entries via the entry type menu  

## 1.1.1 - 2020-05-05
### Fixed  
- Fixes an issue where entry type menus would not be displayed for entries loaded in with AJAX on paginated entry indexes  
- Fixes an issue where Child Me! could redirect to the wrong URL for multi-site installs  

## 1.1.0 - 2020-03-08
### Fixed  
- Fixes an issue where entry type menus could be cut off on Craft 3.4+  
- Fixes issues with multi-site on Craft 3.2+  

### Changed  
- Child Me! now requires Craft 3.2.0  

## 1.0.6 - 2019-10-21
### Fixed
- Fixes an issue where Child Me! could conflict with other plugins

## 1.0.5 - 2019-10-15
### Fixed  
- Fixes an issue where sort order was not respected for entry types. Fixes #2  

## Improved  
- Reduces number of database queries in the Control Panel

## 1.0.4 - 2018-06-05
### Fixed
- Fixes an issue where adding child entries in a multi-site install would always create children for the primary site

## 1.0.3 - 2018-05-23
### Improved
- Child Me! quickmenu now closes immediately when clicking outside the container

### Fixed
– Fixes an issue where the Child Me! JavaScript would get initialised multiple times

## 1.0.2 - 2018-05-23
### Improved
- Entry Type names are now translated using the "site" translation category

## 1.0.1 - 2018-05-01
### Improved
- Improves asset bundle loading to avoid conflicts with other plugins

## 1.0.0 - 2017-12-09
### Added
- Initial release
