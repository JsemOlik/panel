import React from 'react';
import ReactDOM from 'react-dom';
import App from '@/components/App';
import { setConfig } from 'react-hot-loader';
import { applyPrimaryColor, getPrimaryColor } from '@/lib/primaryColor';
import { applyThemePreference, getThemePreference } from '@/lib/theme';

// Enable language support.
import './i18n';

// Prevents page reloads while making component changes which
// also avoids triggering constant loading indicators all over
// the place in development.
//
// @see https://github.com/gaearon/react-hot-loader#hook-support
setConfig({ reloadHooks: false });

// Apply the user's theme and primary color before the first render so the defaults don't flash.
applyThemePreference(getThemePreference());
applyPrimaryColor(getPrimaryColor());

ReactDOM.render(<App />, document.getElementById('app'));
