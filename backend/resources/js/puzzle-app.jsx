import React from 'react';
import { createRoot } from 'react-dom/client';
import BikePuzzle from './components/BikePuzzle';

const el = document.getElementById('bike-puzzle-root');
if (el) {
    createRoot(el).render(<BikePuzzle />);
}
