# UM Import Profile Photos
Extension to Ultimate Member for importing Profile photos. When importing Users via a CSV file the path/URL to the User's profile photo can be saved from a CSV column with the address of the Profile photo. The column header name is the Ultimate Member meta_key name.

## UM Settings -> Appearance -> Profile -> Import Profile Photos
1. *  Meta_key with User Profile photo source address - Profile photo source address can contain either a wp-content path or an URL with 'https://'. Input of 'profile_photo' can't be used here.
2. *  Reuse Meta_key with Profile photo source address - Tick to reuse (for future photo updates) the Profile photo source address meta_key and update UM Profile photo via the source address each time when Profile is viewed.

## Profile photos
### Supported mime types
image/gif, image/jpeg, image/jpg, image/png, image/bmp
### Cropping
Rectangular images are cropped to square images. Vertical rectangle are cropped top square, horizontal mid square.
### Resizing
Images are resized according to UM Settings -> General -> Uploads -> "Profile Photo Thumbnail Sizes (px)"
### Quality
Images are saved with Quality according to UM Settings -> General -> Uploads -> "Image Quality"

## Translations & Text changes
1. For a few changes of text use the "Say What?" plugin with text domain ultimate-member
2. https://wordpress.org/plugins/say-what/

## Updates
None

## Image Moderation - Account File Manager
Profile/Cover photo updates and other uploaded files can be displayed with the <a href="https://github.com/MissVeronica/um-account-file-manager">UM Account File Manager</a> plugin

## Installation & Updates
1. Download the plugin ZIP file at the green Code button
2. Install or Update as a new WP Plugin, activate the plugin.
