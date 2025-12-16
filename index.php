<?php ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MegaJoins Analytics</title>
  <link rel="icon" href="data:,">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
  <h1>MegaJoins Analytics</h1>
  <div class="totals" id="totals">
    <div class="card"><div class="label">All Joins</div><div class="value" id="total-all">—</div></div>
    <div class="card"><div class="label">Unique Joins</div><div class="value" id="total-unique">—</div></div>
    <div class="card"><div class="label">Distinct Hosts</div><div class="value" id="total-hosts">—</div></div>
  </div>
</header>

<section class="filters">
  <form id="filter-form">
    <div class="row">
      <label>Start</label>
      <input type="datetime-local" id="start" name="start">
    </div>
    <div class="row">
      <label>End</label>
      <input type="datetime-local" id="end" name="end">
    </div>
    <div class="row">
      <label>Hostname</label>
      <input list="host-list" id="host" name="host" placeholder="(any)">
      <datalist id="host-list"></datalist>
    </div>
    <div class="row">
      <label>UUID</label>
      <input type="text" id="uuid" name="uuid" placeholder="32-char hex">
    </div>
    <div class="row">
      <label>Player(s)</label>
      <input list="player-list" id="player" name="player" placeholder="Comma-separated">
      <datalist id="player-list"></datalist>
    </div>
    <div class="row">
      <label>Granularity</label>
      <select id="interval" name="interval">
        <option value="">Auto</option>
        <option value="minute">Minute</option>
        <option value="hour">Hour</option>
        <option value="day">Day</option>
      </select>
    </div>
    <div class="row buttons">
      <button type="submit">Apply</button>
      <a id="export" class="btn-outline" href="#">Export CSV</a>
      <button type="button" id="clear">Clear</button>
    </div>
  </form>
</section>

<section class="chart">
  <canvas id="tsChart"></canvas>
</section>

<section class="grid two">
  <div>
    <h2>Top Domains (root)</h2>
    <table id="roots-table">
      <thead><tr><th>Root Domain</th><th>Joins</th><th>Unique</th></tr></thead>
      <tbody></tbody>
    </table>
    <div id="subdomain-drawer" class="drawer hidden">
      <h3 id="drawer-title">Subdomains</h3>
      <table id="subs-table">
        <thead><tr><th>Subdomain</th><th>Joins</th><th>Unique</th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  <div>
    <h2>Recent Joins</h2>
    <table id="recent-table">
      <thead><tr><th>When</th><th>Hostname</th><th>Player</th><th>UUID</th></tr></thead>
      <tbody></tbody>
    </table>
    <div class="pager">
      <button id="prev">Prev</button>
      <span id="page-info"></span>
      <button id="next">Next</button>
    </div>
  </div>
</section>

<div id="tooltip" class="tooltip hidden"></div>

<footer>
  <small>Powered by MegaJoins · PHP + MySQL</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
