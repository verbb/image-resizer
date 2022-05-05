# Image Resizer

Image Resizer is a Craft plugin that resizes your assets when they are uploaded. This allows huge images to be resized so as not to use up unnecessary disk space, but still kept at a reasonable resolution. This plugin is not a replacement for using image transforms throughout your site.

Image Resizer works for all Asset Sources: Local, Rackspace Cloud Files, Amazon S3, and Google Cloud Storage.

## Resizing

Resizing of images can be setup to run automatically (through the plugin settings) whenever new images are uploaded. The aspect ratio of images is maintained, and will always match the maximum width/height options in your plugin settings. For example, given a 4000 x 2500px image and a max width/height of 1024px, the resulting image would be 1024 x 640px.

Please note that resizing of images will **permanently** alter the original uploaded image, so be sure to set the maximum allowed size to something that works for your purposes, while maintaining image quality.

## Batch Resizing

To batch resize images, use the Assets Index to select which image files you'd like to resize, click on the Actions button and select Resize image.

![](/docs/screenshots/elementactions.png)

You'll be presented with a warning screen advising that the selected images will be resized according to your plugin settings.

![](/docs/screenshots/resizeelementaction.png)

Under the hood, the batch processing is run through Craft's Queue service, which will allow you to process plenty of images at once, without timing out or running into memory issues.

Additionally, using the plugin settings page (Bulk Resize tab), you can bulk-resize all assets in a single folder.

## Logs

Each time an image is processed, a log item will be created to provide feedback on the task that has occurred. Particularly useful for resizing images. When using the Element Action, or bulk resizing, you'll be shown a summary of files resized and their state (as below).

![](/docs/screenshots/resizeelementaction-logs.png)

A detailed Log screen shows further detail on each image that's been processed.

![](/docs/screenshots/logs.png)
