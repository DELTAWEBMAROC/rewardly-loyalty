(function () {
  var scheduled = false;

  function hasHtml(value) {
    return typeof value === 'string' && value.trim() !== '';
  }

  function createSlot(html, type) {
    var wrapper = document.createElement('div');
    wrapper.className = 'rewardly-block-slot rewardly-block-slot--' + type;
    wrapper.setAttribute('data-rewardly-block-slot', type);
    wrapper.innerHTML = html;
    return wrapper;
  }

  function findFirst(selectors) {
    for (var i = 0; i < selectors.length; i += 1) {
      var node = document.querySelector(selectors[i]);
      if (node) {
        return node;
      }
    }
    return null;
  }

  function findCartRoot() {
    return findFirst([
      '.wp-block-woocommerce-cart',
      '.wc-block-cart'
    ]);
  }

  function findCheckoutRoot() {
    return findFirst([
      '.wp-block-woocommerce-checkout',
      '.wc-block-checkout'
    ]);
  }

  function findLayoutChild(root) {
    if (!root || typeof root.querySelector !== 'function') {
      return null;
    }

    return root.querySelector(':scope > .wc-block-components-sidebar-layout') ||
      root.querySelector(':scope > .wc-block-cart') ||
      root.querySelector(':scope > .wc-block-checkout') ||
      root.querySelector('.wc-block-components-sidebar-layout');
  }

  function getRoot(type) {
    return type === 'checkout' ? findCheckoutRoot() : findCartRoot();
  }

  function getScopedExisting(root, type) {
    if (!root || typeof root.querySelector !== 'function') {
      return null;
    }
    return root.querySelector('[data-rewardly-block-slot="' + type + '"]');
  }

  function syncSlot(type, html) {
    if (!hasHtml(html)) {
      return;
    }

    var root = getRoot(type);
    if (!root) {
      return;
    }

    var existing = getScopedExisting(root, type);
    var insertBefore = findLayoutChild(root);

    if (existing) {
      if (existing.getAttribute('data-rewardly-html') !== html) {
        existing.innerHTML = html;
        existing.setAttribute('data-rewardly-html', html);
      }

      if (existing.parentNode !== root) {
        root.insertBefore(existing, insertBefore || root.firstChild || null);
      }
      return;
    }

    var slot = createSlot(html, type);
    slot.setAttribute('data-rewardly-html', html);
    root.insertBefore(slot, insertBefore || root.firstChild || null);
  }

  function renderRewardlyBlocks() {
    scheduled = false;

    if (typeof rewardlyBlocksData === 'undefined') {
      return;
    }

    syncSlot('cart', rewardlyBlocksData.cartHtml);
    syncSlot('checkout', rewardlyBlocksData.checkoutHtml);
  }

  function scheduleRender() {
    if (scheduled) {
      return;
    }
    scheduled = true;
    window.requestAnimationFrame(renderRewardlyBlocks);
  }

  document.addEventListener('DOMContentLoaded', scheduleRender);
  window.addEventListener('load', scheduleRender);

  var observer = new MutationObserver(function (mutations) {
    for (var i = 0; i < mutations.length; i += 1) {
      var mutation = mutations[i];

      if (mutation.target && mutation.target.closest && mutation.target.closest('.rewardly-block-slot')) {
        continue;
      }

      scheduleRender();
      break;
    }
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
})();
