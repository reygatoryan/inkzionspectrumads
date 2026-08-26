async function initInkzionApp() {
  const navToggle = document.getElementById('nav-toggle');
  const navList = document.getElementById('nav-list');
  navToggle && navToggle.addEventListener('click', () => {
    const isOpen = navList.classList.toggle('show');
    navToggle.setAttribute('aria-expanded', String(isOpen));
  });

  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  // Search functionality for service items
  const searchInput = document.getElementById('item-search');
  const servicesList = document.getElementById('services-list');
  
  if (searchInput && servicesList) {
    // Store all service items
    const allItems = [
      'SUBLIMATION ROUND NECK T SHIRT',
      'SUBLIMATION POLO SHIRT ZIPPER TYPE/BUTTON TYPE',
      'SUBLIMATION VARSITY JACKET',
      'SUBLIMATION CHINESE COLLAR',
      'SUBLIMATION BASKET BALL JERSEY',
      'TARPAULINS',
      'PANAFLEX',
      'VINYL STICKERS',
      'CALENDARS',
      'CALLING CARDS',
      'LANYARDS',
      'PVC IDs',
      'GIVEAWAYS',
      'PHOTO PRINTING',
      'STAND BANNERS',
      'SIGNAGE',
      'CERTIFICATE PRINTING',
      'MUG',
      'CAPS',
      'DTF',
      'FLYERS',
      'TUMBLER',
      'MOUSE PAD',
      'FULL SUBLIMATION ROUND NECK T SHIRT',
      'FULL SUBLIMATION POLO SHIRT ZIPPER TYPE/BUTTON TYPE',
      'FULL SUBLIMATION VARSITY JACKET',
      'FULL SUBLIMATION CHINESE COLLAR',
      'FULL SUBLIMATION BASKET BALL JERSEY'
    ];

    // Render all items initially
    function renderItems(itemsToShow) {
      servicesList.innerHTML = '';
      itemsToShow.forEach(item => {
        const div = document.createElement('div');
        div.className = 'service-item';
        div.textContent = item;
        servicesList.appendChild(div);
      });
    }

    // Initial render
    renderItems(allItems);

            // Search functionality
    searchInput.addEventListener('input', (e) => {
      const searchTerm = e.target.value.toLowerCase();
      const filtered = allItems.filter(item => 
        item.toLowerCase().includes(searchTerm)
      );
      renderItems(filtered);
    });
  }

  // Request Custom Quote button in products section
  const productsQuoteBtn = document.getElementById('products-quote-btn');
  if (productsQuoteBtn) {
    productsQuoteBtn.addEventListener('click', () => {
      // Scroll to contact form
      const contactSection = document.getElementById('contact');
      if (contactSection) {
        contactSection.scrollIntoView({ behavior: 'smooth' });
        // Focus on the message field for better UX
        setTimeout(() => {
          const messageField = document.getElementById('message');
          if (messageField) messageField.focus();
        }, 600);
      }
    });
  }

}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initInkzionApp);
} else {
  initInkzionApp();
}
