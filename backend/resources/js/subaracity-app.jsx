import React from 'react';
import { createRoot } from 'react-dom/client';
import SubaraCity from './components/SubaraCity';

const el = document.getElementById('subaracity-root');
if (el) {
    createRoot(el).render(<SubaraCity />);
}
