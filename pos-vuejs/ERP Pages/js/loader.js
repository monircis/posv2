// Collects the screen logic from the js/ files and hands it to support.js.
// support.js reads the code from <script data-dc-script> when the page has
// loaded, so each file registers its code with DC.add() and this loader
// writes it all into that element just before support.js boots.
(function () {
  var parts = [];
  window.DC = {
    add: function (fn) {
      var s = fn.toString();
      parts.push(s.slice(s.indexOf('{') + 1, s.lastIndexOf('}')));
    }
  };
  document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('script[data-dc-script]');
    if (el) el.textContent = parts.join('\n');
  });
})();
