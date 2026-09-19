import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';

import { seedImageResizerAssets } from '../../support/fixtures';

let assetCount = 5;

export default defineScreenshotScenario({
    id: 'image-resizer-feature-tour-overview',
    output: 'feature-tour/resizeelementaction.png',
    route: '/admin/image-resizer/settings',
    viewport: { width: 1440, height: 900, deviceScaleFactor: 2 },
    async setup(context) {
        assetCount = (await seedImageResizerAssets(context)).assetCount;
    },
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#main-content', state: 'visible' },
    ],
    steps: [
        {
            type: 'evaluate',
            expression: `
                (() => {
                    const selectedItems = $(Array.from({ length: ${assetCount} }, () => document.body));
                    new Craft.ImageResizer.ResizeModal(selectedItems, selectedItems, { width: '2048', height: '2048' });
                })();
            `,
        },
        { type: 'wait', waitFor: { type: 'selector', selector: '.image-resizer-modal', state: 'visible' } },
        { type: 'wait', waitFor: { type: 'timeout', ms: 250 } },
        {
            type: 'evaluate',
            expression: `
                document.activeElement?.blur();
                const cancelButton = document.querySelector('.image-resizer-modal .cancel');
                cancelButton?.style.setProperty('box-shadow', 'none', 'important');
                cancelButton?.style.setProperty('outline', 'none', 'important');
            `,
        },
        { type: 'wait', waitFor: { type: 'timeout', ms: 100 } },
    ],
    target: { type: 'selector', selector: '.image-resizer-modal', padding: 0 },
    caption: 'Image Resizer on-demand resize dialog with maximum dimensions.',
    intent: 'Show the current Craft 5 resize element action for five real image assets.',
});
