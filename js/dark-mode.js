const btn = document.getElementsByClassName('dark-mode')[0];

btn.addEventListener('click', () => {

    // Check if dark mode style already exists
    let existingStyle = document.getElementById('dark-mode-style');

    if (existingStyle) {
        // Remove dark mode
        existingStyle.remove();
        btn.innerHTML = "<i class=\"bi bi-moon-fill\"></i>";
    } else {
        // Create dark mode
        const css = `
            html {
                filter: invert(1) hue-rotate(180deg);
            }

            img, video, iframe {
                filter: invert(1) hue-rotate(180deg);
            }
        `;
        btn.innerHTML = "<i class=\"bi bi-sun-fill\"></i>";

        const style = document.createElement('style');
        style.id = 'dark-mode-style';
        style.textContent = css;

        document.head.appendChild(style);
    }

});