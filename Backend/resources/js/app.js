//
import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';

const container = document.getElementById('app');

if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <div class="p-8 text-center">
                <h1 class="text-2xl font-bold">Application React chargée avec succès !</h1>
            </div>
        </React.StrictMode>
    );
}