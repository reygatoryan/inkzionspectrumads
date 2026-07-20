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


  const products = [
    {
      category: 'Business Cards',
      items: [
        { name: 'Standard Business Cards', description: '350gsm premium cardstock, 500 pieces', price: '$29', image: 'assets/businesscard/STANDARDCARD.JPG' },
        { name: 'Premium Embossed Cards', description: 'Raised ink with luxe finish, 500 pieces', price: '$59', image: 'assets/businesscard/embossed-cards.png' },
        { name: 'Metal Business Cards', description: 'Stainless steel with engraving, 100 pieces', price: '$149', image: 'assets/businesscard/metal-cards.png' },
        { name: 'Calling Cards', description: 'Elegant calling cards, 1000 pieces', price: '$39', image: 'assets/businesscard/calling-cards.png' },
      ],
    },
    {
      category: 'Marketing Materials',
      items: [
        { name: 'Tri-Fold Brochures', description: 'Full color, double-sided, 500 pieces', price: '$89', image: 'assets/marketing/brochures.png' },
        { name: 'Flyers & Postcards', description: 'Glossy or matte finish, 1000 pieces', price: '$39', image: 'assets/marketing/flyers.png' },
        { name: 'Calendars', description: 'Custom printed wall or desk calendars', price: '$49', image: 'assets/marketing/calendars.png' },
        { name: 'Certificate Printing', description: 'Professional certificates with borders, 100 pieces', price: '$29', image: 'assets/marketing/certificates.png' },
      ],
    },
    {
      category: 'Large Format & Signage',
      items: [
        { name: 'Vinyl Banners', description: 'Indoor & outdoor durability, custom sizes', price: '$79', image: 'https://via.placeholder.com/600x400/e91e8c/ffffff?text=Vinyl+Banners' },
        { name: 'Stand Banners', description: 'Retractable banner stands, premium quality', price: '$199', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Stand+Banners' },
        { name: 'Signage', description: 'Custom shop signs and directional signage', price: '$149', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Signage' },
        { name: 'Tarpaulins & Panaflex', description: 'Heavy-duty tarpaulins and flexible signage', price: '$129', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Tarpaulins' },
      ],
    },
    {
      category: 'Apparel & Sublimation',
      items: [
        { name: 'Sublimation Round Neck T-Shirt', description: 'Full color printing, sizes XS-2XL', price: '$15', image: 'https://via.placeholder.com/600x400/e91e8c/ffffff?text=T-Shirts' },
        { name: 'Sublimation Polo Shirt', description: 'Zipper or button type, professional look', price: '$25', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Polo+Shirt' },
        { name: 'Sublimation Varsity Jacket', description: 'Premium fabric, custom design', price: '$59', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Varsity+Jacket' },
        { name: 'Basketball Jersey & Uniforms', description: 'Chinese collar or basketball styles', price: '$35', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Basketball' },
      ],
    },
    {
      category: 'Custom Merchandise',
      items: [
        { name: 'Custom Mugs', description: '11oz ceramic with full color wrap', price: '$12', image: 'https://via.placeholder.com/600x400/e91e8c/ffffff?text=Custom+Mugs' },
        { name: 'Tumblers', description: 'Stainless steel, insulated, 20oz', price: '$18', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Tumblers' },
        { name: 'Custom Caps & Hats', description: 'Embroidered or printed logos', price: '$14', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Caps+and+Hats' },
        { name: 'Mouse Pads', description: 'Non-slip rubber base, custom design', price: '$8', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Mouse+Pads' },
      ],
    },
    {
      category: 'Promotional Items & Giveaways',
      items: [
        { name: 'Vinyl Stickers', description: 'Die-cut custom shapes, waterproof', price: '$19', image: 'https://via.placeholder.com/600x400/e91e8c/ffffff?text=Vinyl+Stickers' },
        { name: 'Lanyards', description: 'Custom printed with logo', price: '$5', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=Lanyards' },
        { name: 'PVC IDs & Cards', description: 'Professional ID cards, 100 pieces', price: '$29', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=PVC+IDs' },
        { name: 'Giveaways & Promotional Items', description: 'Branded merchandise for events', price: '$10+', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Giveaways' },
      ],
    },
    {
      category: 'Specialty Printing',
      items: [
        { name: 'Photo Printing', description: 'High-quality 4x6 to 16x20 prints', price: '$5-$29', image: 'https://via.placeholder.com/600x400/e91e8c/ffffff?text=Photo+Printing' },
        { name: 'DTF (Direct-to-Film) Printing', description: 'Premium transfer printing quality', price: '$8', image: 'https://via.placeholder.com/600x400/00bcd4/ffffff?text=DTF+Printing' },
        { name: 'Canvas Prints', description: 'Gallery-wrapped finish, custom sizes', price: '$49', image: 'https://via.placeholder.com/600x400/ffc107/ffffff?text=Canvas+Prints' },
        { name: 'Presentation Folders', description: 'Custom pockets & die-cuts', price: '$99', image: 'https://via.placeholder.com/600x400/8bc34a/ffffff?text=Presentation+Folders' },
      ],
    },
  ];

  const tabsContainer = document.getElementById('product-tabs');
  const productGrid = document.getElementById('product-grid');
  let activeCategory = products[0].category;

  function renderTabs() {
    if (!tabsContainer) return;
    tabsContainer.innerHTML = '';
    products.forEach((product) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'tab-button' + (product.category === activeCategory ? ' active' : '');
      button.textContent = product.category;
      button.addEventListener('click', () => {
        activeCategory = product.category;
        renderTabs();
        renderProducts();
      });
      tabsContainer.appendChild(button);
    });
  }

  
  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderProducts() {
    if (!productGrid) return;
    const category = products.find((product) => product.category === activeCategory);
    if (!category) return;
    productGrid.innerHTML = category.items.map((item) => `
      <article class="product-card" data-product-name="${item.name}">
        <a href="product-details.php?product=${encodeURIComponent(item.name)}" class="product-card-link">
          <div class="product-image"><img src="${item.image}" alt="${item.name}"></div>
          <div class="product-info">
            <h3>${item.name}</h3>
            <p>${item.description}</p>
          </div>
        </a>
        <div class="product-footer">
          <div class="price">${item.price}</div>
          <div class="product-actions">
            <button class="btn-customize" data-product="${item.name}">Customize</button>
          </div>
        </div>
      </article>
    `).join('');
    
    // Add event listeners to customize buttons - redirect to contact form with product info
    document.querySelectorAll('.btn-customize').forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const productName = button.dataset.product;
        // Store product name in session/local storage for pre-filling
        localStorage.setItem('customizeProduct', productName);
        // Redirect to home with contact section
        window.location.href = 'index.php#contact';
      });
    });
  }

  renderTabs();
  renderProducts();

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

  const contactForm = document.getElementById('contact-form');
  const status = document.getElementById('form-status');

  // Pre-fill contact form if user clicked "Request Customization"
  if (contactForm) {
    const customizeProduct = localStorage.getItem('customizeProduct');
    if (customizeProduct) {
      const messageField = document.getElementById('message');
      if (messageField) {
        messageField.value = `I'm interested in customizing: ${customizeProduct}. Please provide details on how we can make this product special for our needs.`;
        messageField.focus();
      }
      // Clear the stored product name
      localStorage.removeItem('customizeProduct');
    }
  }

    // Password show/hide toggle
    (function setupPasswordToggles() {
      document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.password-toggle-btn');
        if (!toggleBtn) return;
        e.preventDefault();
        const wrapper = toggleBtn.closest('.password-input-wrapper');
        if (!wrapper) return;
        const input = wrapper.querySelector('input');
        if (!input) return;
        const icon = toggleBtn.querySelector('i');
        if (input.type === 'password') {
          input.type = 'text';
          if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
        } else {
          input.type = 'password';
          if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
      });
    })();


  }

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initInkzionApp);
} else {
  initInkzionApp();
}