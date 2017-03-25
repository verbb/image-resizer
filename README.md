# Image Resizer

Image Resizer is a Craft plugin that resizes your assets when they are uploaded. This allows huge images to be resized so as not to use up unnecessary disk space, but still kept at a reasonable resolution. This plugin is not a replacement for using image transforms throughout your site.

Image Resizer works for all Asset Sources: Local, Rackspace Cloud Files, Amazon S3, and Google Cloud Storage.

## Install

- Add the `imageresize` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin options**

- Enable/Disable resizing images on upload. Enabled by default.
- Set the maximum width/height (in pixels) for uploaded images. Set to 2048px by default.
- Set the quality for resized images between 0-100. Set to 100 by default.
- If resizing results in a larger image, choose to ship. Enabled by default.

All the above options can be modified per-asset source.

- Bulk resizing for asset folders.
- Cropping aspect ratios.


## Resizing

Resizing of images can be setup to run automatically (through the plugin settings) whenever new images are uploaded. The aspect ratio of images are maintained, and will always match the maximum width/height options in your plugin settings. For example, given a 4000 x 2500px image and a max width/height of 1024px, the resulting image would be 1024 x 640px.

Please note that resizing of images will **permanently** alter the original uploaded image, so be sure to set the maximum allowed size to something that works for your purposes, while maintaining image quality.


## Batch Resizing

To batch resize images, use the Assets Index to select which image files you'd like to resize, click on the Actions button and select Resize image.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/elementactions.png" width="250" />

You'll be presented with a warning screen advising that the selected images will be resized according to your plugin settings.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/resizeelementaction.png" width="400" />

Under the hood, the batch processing is run through Craft's Tasks service, which will allow you to process plenty of images at once, without timing out or running into memory issues.

Additionally, using the plugin settings page (Bulk Resize tab), you can bulk-resize all assets in a single folder. 


## Cropping

You can crop any image through the Assets Index screen, by clicking on the Actions button, and selecting Crop image. You can only crop one image at a time. There are several preset options related to the aspect ratio to control how cropping is controlled, and are selected through the Crop modal window.

Activating the cropping interface upon upload of new images is on the Roadmap, and will be released soon.

You can manage these aspect ratios through the plugin settings page, including removing/renaming existing options, or adding your own.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/cropping.png" width="400" />

Default aspect ratio options are:

- **Free:** No restrictions 
- **Square:** Restricted to square crop
- **Constrain:** Restricted to the aspect ratio of the image
- **4:3:** Restricted to a 4:3 aspect ratio crop


## Logs

Each time an image is processed (resized or cropped), a log item will be created to provide feedback on the task that has occured. Particularly useful for resizing images. When using the Element Action, or bulk resizing, you'll be shown a summary of files resized and their state (as below).

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/resizeelementaction-logs.png" width="400" />

A detailed Log screen shows further detail on each image that's been processed.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/logs.png" />

## Roadmap

- Provide cropping options on-upload.
- Add more features for full-featured image editor.


## Compatibility

Requires Craft 2.5+ to run.


## Changelog

[View JSON Changelog](https://github.com/engram-design/ImageResizer/blob/master/changelog.json)
