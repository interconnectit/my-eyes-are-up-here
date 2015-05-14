=== My Eyes Are Up Here ===
Contributors: interconnectit, sanchothefat, spectacula, AndyWalmsley
Donate link: https://myeyesareuphere.interconnectit.com/donate/
Tags: thumbnails, image editing, image, featured image
Requires at least: 3.8.1
Tested up to: 4.0
Stable tag: 1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

My Eyes Are Up Here helps you control how WordPress generates thumbnails.

== Description == 

= What is it? =
A fantastic new plugin that helps you control how WordPress generates thumbnails.

= Why use it? =
When WordPress automatically generates thumbnails, it sometimes doesn't crop them in a way that is suitable for the image you've uploaded. If your image isn't the correct format, and let's face it, you never know what images people are uploading - you'll run the risk of a badly cropped image. Not good.
If you have a full portrait image of a person that you've uploaded, but you need the image to appear landscape, you're in trouble! WordPress will centre the image so that you end up with person's crotch. Not good. Or let's say you have a landscape image, with a person's face on the right hand side, but you need it to display in a square thumbnail. You'll end up with half a face as WordPress centres the image.

= What does the plugin do? =
You can control how you want your WordPress thumbnails to appear on your website. Regardless of the image format you upload, you can either use the automatic face detector or if you want even more control, you can manually add hotspots.

= How do I use it? =
Navigate to your media library then click on the image you want to edit. Use the detect faces or edit hotspots option to edit your image. You'll see thumbnail previews when you've applied these edits, when you're happy hit update. Simple. 

== Installation ==

= The install =
1. You can install the plugin using the auto-install tool from the WordPress back-end.
2. To manually install, upload the folder `/myeyesareuphere/` to `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress

== Usage ==

1. Once the plugin is activated, navigate to your 'Media Library'.
2. Click on the image you want to edit to bring up your 'Edit Media' options.
3. You should now be able to see extra image editing options, below the 'Description' box.
4. By clicking 'Detect faces' or 'Add hotspots' you can now start to edit your image thumbnails.
5. If you click the 'Detect faces' button, it will centre the crop using an average of all the faces it finds.
5. Please note this is basic face detection and won't find everything. 
6. You can click and create 1 or several hotspots to centre the crop of your thumbnails, if 'Detect faces' doesn't work.   
7. If you're happy with your 'Thumb Previews' hit save, and you're done. Simple. 

== Frequently Asked Questions ==

= What happens when there are multiple hotspots/faces detected? =

This will crop the image to get as many hotspots in the thumbnail as possible or crop around the center of the hotspots if not.

= How do I report a problem? =

You can email us at cases@interconnectit.fogbugz.com with "My Eyes Are Up Here" in the subject and the following information:

1. What browser and version is this problem occurring with?
2. What WordPress version are you using?
3. What version of My Eyes Are Up Here are you using?
4. Are there any errors in the javascript console?
	* Chrome and Firefox: ctrl + shift + j
	* Internet Explorer: F12 and click on 'Script' then 'Console'
5. What are the steps you used to produce this problem?

== Screenshots ==

1. Default WordPress cropping where the thumbnail is cropped to the centre of the image.

2. Thumbnail after My Eyes Are Up Here has detected any faces in the image.

3. How WordPress crops the image without My Eyes Are Up Here.

4. How the image appears once My Eyes Are Up Here has been installed and applied.

== Changelog ==

= 1.2 =
* Changes to readme.

= 1.1 =
* Extra screenshots added

= 1.0 =
* Release version

= 0.4 =
* Bugfixes, play nicely with other plugins/themes that modify image sizes

= 0.3 =
* Hotspots!

= 0.2: =
* jQuery option for speed

== Upgrade Notice ==

= 1.2 =
* Changes to readme.

= 1.1 =
* Extra screenshots added

= 1.0 =
* Release version

= 0.4 =
* Lot's of bugfixes