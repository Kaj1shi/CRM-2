(function () {
  const shell = document.getElementById("shell");
  const toggle = document.getElementById("nav-toggle");
  const menu = document.getElementById("menu-open");
  const bars = document.querySelectorAll("[data-bar]");

  function paint(close) {
    bars.forEach((bar) => {
      const which = bar.getAttribute("data-bar");
      if (which === "1") bar.style.transform = close ? "translateY(-50%) rotate(45deg)" : "translateY(calc(-50% - 5px))";
      if (which === "2") {
        bar.style.transform = "translateY(-50%)";
        bar.style.opacity = close ? "0" : "1";
      }
      if (which === "3") bar.style.transform = close ? "translateY(-50%) rotate(-45deg)" : "translateY(calc(-50% + 5px))";
    });
  }

  if (shell && localStorage.getItem("crm-collapsed") === "1" && window.matchMedia("(min-width: 768px)").matches) {
    shell.classList.add("is-collapsed");
  }
  paint(!(shell && shell.classList.contains("is-collapsed")));

  if (toggle && shell) {
    toggle.addEventListener("click", () => {
      if (shell.classList.contains("is-open")) {
        shell.classList.remove("is-open");
        paint(true);
        return;
      }
      const collapsed = shell.classList.toggle("is-collapsed");
      localStorage.setItem("crm-collapsed", collapsed ? "1" : "0");
      paint(!collapsed);
      toggle.setAttribute("aria-label", collapsed ? "Expand sidebar" : "Collapse sidebar");
    });
  }
  if (menu && shell) {
    menu.addEventListener("click", () => {
      shell.classList.add("is-open");
      paint(true);
    });
  }

  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    button.addEventListener("click", () => {
      const input = document.getElementById(button.getAttribute("data-password-toggle"));
      if (!input) return;
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      button.setAttribute("aria-pressed", show ? "true" : "false");
      button.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  const typeSelect = document.getElementById("transaction-type");
  function syncParty() {
    if (!typeSelect) return;
    const purchase = typeSelect.value === "CLIENT_PURCHASE";
    document.querySelectorAll("[data-party]").forEach((node) => {
      node.hidden = node.getAttribute("data-party") === "client" ? !purchase : purchase;
    });
  }
  if (typeSelect) {
    typeSelect.addEventListener("change", syncParty);
    syncParty();
  }

  const search = document.getElementById("global-search");
  const results = document.getElementById("search-results");
  const base = document.body.dataset.base || "";
  let timer = 0;
  if (search && results) {
    search.addEventListener("input", () => {
      window.clearTimeout(timer);
      const value = search.value.trim();
      if (value.length < 2) {
        results.classList.add("hidden");
        results.innerHTML = "";
        return;
      }
      timer = window.setTimeout(async () => {
        const response = await fetch(base + "/search?q=" + encodeURIComponent(value), { headers: { Accept: "application/json" } });
        const data = await response.json();
        const items = [];
        (data.clients || []).forEach((item) => items.push('<a class="block rounded px-2 py-1.5 hover:bg-slate-100" href="' + base + "/clients/" + encodeURIComponent(item.id) + '">' + escapeHtml(item.name) + "</a>"));
        (data.suppliers || []).forEach((item) => items.push('<a class="block rounded px-2 py-1.5 hover:bg-slate-100" href="' + base + "/suppliers/" + encodeURIComponent(item.id) + '">' + escapeHtml(item.name) + "</a>"));
        (data.transactions || []).forEach((item) => items.push('<a class="block rounded px-2 py-1.5 hover:bg-slate-100" href="' + base + '/transactions">' + escapeHtml(item.transactionCode) + "</a>"));
        results.innerHTML = items.join("") || '<p class="px-2 py-1.5 text-slate-500">No matches.</p>';
        results.classList.remove("hidden");
      }, 180);
    });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char]));
  }

  document.querySelectorAll("canvas[data-chart]").forEach((canvas) => drawChart(canvas));

  function drawChart(canvas) {
    const spec = JSON.parse(canvas.dataset.chart);
    const parent = canvas.parentElement;
    const width = parent.clientWidth || 480;
    const height = 240;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    canvas.style.width = width + "px";
    canvas.style.height = height + "px";
    const ctx = canvas.getContext("2d");
    ctx.scale(dpr, dpr);
    const pad = { l: 42, r: 12, t: 12, b: 28 };
    const rows = spec.rows || [];
    const values = rows.map((row) => Number(row[spec.key] || 0));
    const max = Math.max(1, ...values);
    ctx.clearRect(0, 0, width, height);
    ctx.strokeStyle = "#e7e5e4";
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = pad.t + ((height - pad.t - pad.b) * i) / 4;
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(width - pad.r, y);
      ctx.stroke();
    }
    ctx.fillStyle = "#78716c";
    ctx.font = "11px sans-serif";
    if (rows.length === 0) {
      ctx.fillText("No records yet", pad.l, height / 2);
      return;
    }
    if (spec.kind === "line") {
      ctx.beginPath();
      rows.forEach((row, index) => {
        const x = pad.l + (rows.length === 1 ? (width - pad.l - pad.r) / 2 : (index * (width - pad.l - pad.r)) / (rows.length - 1));
        const y = pad.t + (1 - Number(row[spec.key] || 0) / max) * (height - pad.t - pad.b);
        if (index === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.strokeStyle = spec.color;
      ctx.lineWidth = 2;
      ctx.stroke();
    } else {
      const gap = (width - pad.l - pad.r) / rows.length;
      rows.forEach((row, index) => {
        const barH = (Number(row[spec.key] || 0) / max) * (height - pad.t - pad.b);
        const x = pad.l + index * gap + gap * 0.2;
        const y = height - pad.b - barH;
        ctx.fillStyle = spec.color;
        ctx.fillRect(x, y, gap * 0.6, barH);
      });
    }
    ctx.fillStyle = "#78716c";
    rows.forEach((row, index) => {
      if (rows.length > 8 && index % 2) return;
      const x = pad.l + (rows.length === 1 ? 0 : (index * (width - pad.l - pad.r)) / Math.max(1, rows.length - 1));
      ctx.fillText(String(row.label || ""), x, height - 8);
    });
  }
})();
