import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './warashibe/App';

const el = document.getElementById('warashibe-root');
if (el) {
    createRoot(el).render(<App />);
}
