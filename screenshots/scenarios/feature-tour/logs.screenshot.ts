import { defineScreenshotScenario } from '@verbb/craft-screenshots/api';
import { seedImageResizerLogs } from '../../support/fixtures';

export default defineScreenshotScenario({
    id: 'image-resizer-feature-tour-logs',
    output: 'feature-tour/logs.png',
    route: '/admin/image-resizer/logs',
    viewport: { width: 1200, height: 760, deviceScaleFactor: 2 },
    setup: seedImageResizerLogs,
    waitFor: [
        { type: 'loadState', state: 'networkidle' },
        { type: 'selector', selector: '#logs tbody tr', state: 'visible' },
    ],
    target: { type: 'selector', selector: '#logs', padding: 0 },
    caption: 'Image Resizer log showing successful, skipped and failed operations.',
    intent: 'Recreates the production log image with deterministic current Craft 5 entries.',
});
