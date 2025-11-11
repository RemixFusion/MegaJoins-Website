(function(){
  const qs = (s,el=document)=>el.querySelector(s);
  const qsa = (s,el=document)=>[...el.querySelectorAll(s)];
  const fmtNum = (n)=>Number(n).toLocaleString();
  const toDashedUuid = (hex)=> hex.length===32 ? [hex.slice(0,8),hex.slice(8,12),hex.slice(12,16),hex.slice(16,20),hex.slice(20)].join('-') : hex;

  // Defaults: last 7 days
  const now = new Date();
  const start = new Date(now.getTime()-6*24*3600*1000);
  qs('#start').value = new Date(start.getTime()-start.getTimezoneOffset()*60000).toISOString().slice(0,16);
  qs('#end').value = new Date(now.getTime()-now.getTimezoneOffset()*60000).toISOString().slice(0,16);

  // Populate datalists
  fetch('api.php?action=distinct').then(r=>r.json()).then(data=>{
    const hostDL = qs('#host-list'); hostDL.innerHTML='';
    data.hosts.forEach(h=>{ const o=document.createElement('option'); o.value=h.hostname; hostDL.appendChild(o); });
    const playerDL = qs('#player-list'); playerDL.innerHTML='';
    data.players.forEach(p=>{ const o=document.createElement('option'); o.value=p.player_name; playerDL.appendChild(o); });
  }).catch(()=>{});

  const form = qs('#filter-form');
  const exportLink = qs('#export');

  function params(extra={}){
    const p = new URLSearchParams();
    ['start','end','host','uuid','player','interval'].forEach(id=>{
      const v = qs('#'+id).value.trim();
      if (v) p.set(id, v);
    });
    if (extra.page) p.set('page', extra.page);
    if (extra.per) p.set('per', extra.per);
    return p;
  }

  let page = 1, per = 25;

  async function loadAll(){
    // Summary
    const s = await (await fetch('api.php?action=summary&'+params())).json();
    if (s.totals) {
      qs('#total-all').innerText = fmtNum(s.totals.all);
      qs('#total-unique').innerText = fmtNum(s.totals.unique);
      qs('#total-hosts').innerText = fmtNum(s.totals.distinct_hosts);
    }

    // Timeseries
    const t = await (await fetch('api.php?action=timeseries&'+params())).json();
    const labels = t.rows.map(r=>r.d);
    const seriesAll = t.rows.map(r=>Number(r.c));
    const seriesUniq = t.rows.map(r=>Number(r.u));
    updateChart(labels, seriesAll, seriesUniq);

    // Top roots
    const h = await (await fetch('api.php?action=top-hosts-root&'+params())).json();
    const tb = qs('#roots-table tbody'); tb.innerHTML='';
    h.rows.forEach(r=>{
      const tr = document.createElement('tr');
      const a = document.createElement('a'); a.href='#'; a.textContent=r.root;
      a.addEventListener('click', e=>{ e.preventDefault(); showSubdomains(r.root); });
      tr.innerHTML = `<td></td><td>${fmtNum(r.c)}</td><td>${fmtNum(r.u)}</td>`;
      tr.children[0].appendChild(a);
      tb.appendChild(tr);
    });

    // Recent joins
    const r = await (await fetch('api.php?action=recent&'+params({page,per}))).json();
    const rb = qs('#recent-table tbody'); rb.innerHTML='';
    r.rows.forEach(row=>{
      const date = new Date(row.ts*1000);
      const when = date.toLocaleString();
      const tr = document.createElement('tr');
      const hostA = `<a href="#" data-host="${row.hostname}">${row.hostname}</a>`;
      const playerA = `<a href="#" class="player" data-player="${row.player_name}">${row.player_name}</a>`;
      const uuidA = `<a href="#" class="uuid" data-uuid="${row.uuid}">${row.uuid}</a>`;
      tr.innerHTML = `<td>${when}</td><td>${hostA}</td><td>${playerA}</td><td>${uuidA}</td>`;
      rb.appendChild(tr);
    });
    qs('#page-info').textContent = `Page ${page} of ${Math.max(1, Math.ceil(r.total/per))}`;

    // Drilldown & filters
    rb.querySelectorAll('a[data-host]').forEach(a => a.addEventListener('click', e=>{ e.preventDefault(); qs('#host').value=a.dataset.host; page=1; loadAll(); }));
    rb.querySelectorAll('a.player').forEach(a => {
      a.addEventListener('mouseover', (e)=>showPlayerTooltip(e, a.dataset.player));
      a.addEventListener('mousemove', positionTooltip);
      a.addEventListener('mouseout', hideTooltip);
      a.addEventListener('click', e=>{ e.preventDefault(); qs('#player').value=a.dataset.player; page=1; loadAll(); });
    });
    rb.querySelectorAll('a.uuid').forEach(a => a.addEventListener('click', e=>{
      e.preventDefault();
      const dashed = toDashedUuid(a.dataset.uuid);
      window.open(`https://bans.megacrafting.com/player/${dashed}`, '_blank');
    }));

    // Export link with current filters
    exportLink.href = 'export.php?'+params();
  }

  // Subdomain drawer
  async function showSubdomains(root){
    qs('#drawer-title').innerText = `Subdomains for ${root}`;
    const data = await (await fetch('api.php?action=subdomains&root='+encodeURIComponent(root)+'&'+params())).json();
    const tb = qs('#subs-table tbody'); tb.innerHTML='';
    data.rows.forEach(r=>{
      const tr = document.createElement('tr');
      const a = document.createElement('a'); a.href='#'; a.textContent=r.hostname;
      a.addEventListener('click', e=>{ e.preventDefault(); qs('#host').value=r.hostname; page=1; loadAll(); });
      tr.innerHTML = `<td></td><td>${fmtNum(r.c)}</td><td>${fmtNum(r.u)}</td>`;
      tr.children[0].appendChild(a);
      tb.appendChild(tr);
    });
    qs('#subdomain-drawer').classList.remove('hidden');
  }

  // Player tooltip
  let tooltipTimer=null;
  async function showPlayerTooltip(evt, player){
    clearTimeout(tooltipTimer);
    const tip = qs('#tooltip');
    tip.innerHTML = 'Loading…';
    positionTooltip(evt);
    tip.classList.remove('hidden');
    try {
      const data = await (await fetch('api.php?action=player-stats&player='+encodeURIComponent(player)+'&'+params())).json();
      if (!data.rows) { tip.innerHTML='No data'; return; }
      const byRoot = {};
      data.rows.forEach(r=>{
        if (!byRoot[r.root]) byRoot[r.root] = { total:0, uniq:0, subs:[] };
        byRoot[r.root].total += Number(r.c);
        byRoot[r.root].uniq += Number(r.u);
        byRoot[r.root].subs.push({hostname:r.hostname, c:Number(r.c), u:Number(r.u)});
      });
      let html = `<strong>${player}</strong><br/><table><thead><tr><th>Root</th><th>Total</th><th>Unique</th></tr></thead><tbody>`;
      Object.entries(byRoot).forEach(([root,val])=>{
        html += `<tr><td>${root}</td><td>${fmtNum(val.total)}</td><td>${fmtNum(val.uniq)}</td></tr>`;
        val.subs.slice(0,6).forEach(s=>{
          html += `<tr><td style="padding-left:12px;">${s.hostname}</td><td>${fmtNum(s.c)}</td><td>${fmtNum(s.u)}</td></tr>`;
        });
      });
      html += `</tbody></table>`;
      tip.innerHTML = html;
    } catch(e){
      tip.innerHTML = 'Error loading';
    }
  }
  function positionTooltip(e){
    const tip = qs('#tooltip');
    const pad = 10;
    tip.style.left = (e.clientX + pad) + 'px';
    tip.style.top = (e.clientY + pad) + 'px';
  }
  function hideTooltip(){ tooltipTimer=setTimeout(()=>qs('#tooltip').classList.add('hidden'), 150); }

  // Pagination + form
  qs('#prev').addEventListener('click', ()=>{ if (page>1){ page--; loadAll(); } });
  qs('#next').addEventListener('click', ()=>{ page++; loadAll(); });
  form.addEventListener('submit', e=>{ e.preventDefault(); page=1; loadAll(); });
  qs('#clear').addEventListener('click', ()=>{ qsa('#filter-form input').forEach(i=>i.value=''); qs('#start').value=''; qs('#end').value=''; qs('#interval').value=''; page=1; loadAll(); });

  // Chart
  let chart;
  function updateChart(labels, all, uniq){
    if (!chart) {
      chart = new Chart(qs('#tsChart').getContext('2d'), {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'All Joins', data: all, tension: .25 },
            { label: 'Unique Joins', data: uniq, tension: .25 }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { grid:{display:false} }, y: { beginAtZero:true } },
          plugins: { legend: { position: 'bottom' } }
        }
      });
    } else {
      chart.data.labels = labels;
      chart.data.datasets[0].data = all;
      chart.data.datasets[1].data = uniq;
      chart.update();
    }
    qs('#tsChart').parentElement.style.height = '360px';
  }

  // Initial load
  loadAll();
})();
