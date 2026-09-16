import React from 'react';
import ReactDOM from 'react-dom';
import App from '@/components/App';
import { setConfig } from 'react-hot-loader';
import { applyPrimaryColor, getPrimaryColor } from '@/lib/primaryColor';

// Enable language support.
import './i18n';

// Prevents page reloads while making component changes which
// also avoids triggering constant loading indicators all over
// the place in development.
//
// @see https://github.com/gaearon/react-hot-loader#hook-support
setConfig({ reloadHooks: false });

// Apply the user's primary color before the first render so the default doesn't flash.
applyPrimaryColor(getPrimaryColor());

ReactDOM.render(<App />, document.getElementById('app'));
