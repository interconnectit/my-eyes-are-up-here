My eyes are up here
===================

Face detection for generating cropped thumbnails in WordPress. Avoiding automatically generated crotch shots since 2013.

## Why would I want this?

Consider a common problem with automatically generated thumbnails in WordPress themes. You need an image of a precise width and height to fit into the design but you never know what images people are uploading.

You could control the width and height of the standard WP thumbnail sizes and let folks alter the crop as necessary themselves but if you have more than a few custom image sizes you can't alter the crop of those.

Let's say you have a portrait image of someone and your theme needs a landscape crop of the image. WP centers the crop so you'll get an image of the persons crotch... Not ideal. I assume.

This plugin detects faces in an image and centers the crop using an average of all the faces it finds.

```
Portrait image:

+-----------+
|           |
|     O     |
|   --|--   |
|     |     |
|    | |    |
|    | |    |
|           |
+-----------+

Cropped landscape version with default WP cropping:

+-----------+
|   --|--   |
|     |     |
|    | |    |
+-----------+

Cropped landscape version using this plugin:

+-----------+
|           |
|     O     |
|   --|--   |
+-----------+
```