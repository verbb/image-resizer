# Permissions

Image Resizer's permissions decide who can resize existing images from the Assets index and who can check the results. Images resized automatically on upload don't need a permission, because that follows the plugin settings for everyone. Set them in **Settings → Users → User Groups**, or on an individual user's **Permissions** tab, under the **Image Resizer** heading.

| Permission | What It Allows |
| --- | --- |
| Resize images | Use the **Resize image** action on selected images in the Assets index. |
| View all resize logs | Open the resize logs, including from the **View logs** link in the summary after a resize. |
| Clear all resize logs | Clear the resize logs. |

**Clear all resize logs** is nested under **View all resize logs**. **Resize images** also needs Craft's permission to replace files in the asset's volume, and for files uploaded by other people, to replace their files too, because resizing overwrites the original image.

Admins can always do all of this. The plugin settings, including bulk resizing a whole folder, are for admins only.

Resizing replaces the original file and can't be undone, so give **Resize images** to people who look after the asset library, and suggest they [check one image](image-resizer.md#check-one-image-before-a-bulk-resize) before resizing a larger selection.
