# Image Resizer

Image Resizer is a Craft plugin that resizes your assets when they are uploaded. This allows huge images to be resized so as not to use up unnecessary disk space, but still kept at a reasonable resolution. This plugin is not a replacement for using image transforms throughout your site.

The aspect ratio of images are maintained, and will always match the maximum width/height options in your plugin settings. For example, given a 4000 x 2500px image and a max width/height of 1024px, the resulting image would be 1024 x 640px.

## Install

- Add the `imageresize` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin options**

- Enable/Disable resizing images on upload. Enabled by default.
- Set the maximum width/height (in pixels) for uploaded images. Set to 2048px by default.
- Set the quality for resized images between 0-100. Set to 100 by default.
- Select which Asset sources you want resizing to be performed on.

## Batch processing

To batch process any images, use the Assets Index to select which image files you'd like to resize, click on the Actions button and select Resize image.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/elementactions.png" width="250" />

You'll be presented with a warning screen advising that the selected images will be resized according to your plugin settings.

<img src="https://raw.githubusercontent.com/engram-design/ImageResizer/master/screenshots/resizeelementaction.png" width="400" />


## Roadmap

- Provide cropping options for uploaded images.


## Changelog

#### 0.0.3

- Added batch processing for existing assets.
- Added image quality option.

#### 0.0.2

- Moved hook from `onBeforeSaveAsset` to `onSaveAsset`.
- Asset record is updated after resize, reflecting new image width/height/size.
- Added option to restrict resizing to specific Asset sources.

#### 0.0.1

- Initial release.
