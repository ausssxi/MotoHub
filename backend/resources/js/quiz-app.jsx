import React from 'react';
import { createRoot } from 'react-dom/client';
import BikeQuiz from './components/BikeQuiz';

const el = document.getElementById('bike-quiz-root');
if (el) {
    createRoot(el).render(<BikeQuiz />);
}
