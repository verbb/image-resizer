# Image Resizer

Image Resizer is a Craft plugin that resizes your assets when they are uploaded. This allows huge images to be resized so as not to use up unnecessary disk space, but still kept at a reasonable resolution. This plugin is not a replacement for using image transforms throughout your site.

The aspect ratio of images are maintained, and will always match the maximum width/height options in your plugin settings. For example, given a 4000 x 2500px image and a max width/height of 1024px, the resulting image would be 1024 x 640px.

## Install

- Add the `imageresize` directory into your `craft/plugins` directory.
- Navigate to Settings -> Plugins and click the "Install" button.

**Plugin options**

- Enable/Disable resizing images on upload.
- Set the maximum width/height (in pixels) for uploaded images. Set to 2048px by default.


## Roadmap

- Batch processing of existing assets.
- Update assetRecord after image resize, to reflect new size.
- Restrict to specific Assets sources.
- Add image quality option.


## Changelog

#### 0.0.1

- Initial release.