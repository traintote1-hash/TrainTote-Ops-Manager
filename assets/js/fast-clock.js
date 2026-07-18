(() => {
  const root = document.querySelector('[data-fast-clock]');
  if (!root) return;
  let state = JSON.parse(root.dataset.state);
  let receivedAt = Date.now();
  const time = root.querySelector('[data-fast-clock-time]');
  const ratio = root.querySelector('[data-fast-clock-ratio]');
  const paused = root.querySelector('[data-fast-clock-paused]');
  const status = root.querySelector('[data-fast-clock-status]');

  const format = seconds => new Intl.DateTimeFormat(undefined, {hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'UTC'}).format(new Date(seconds * 1000));
  const render = () => {
    const elapsed = state.running ? Math.max(0, Date.now() - receivedAt) / 1000 : 0;
    time.textContent = format((state.model_seconds + elapsed * state.ratio) % 86400);
    ratio.textContent = `${state.ratio}:1`;
    paused.hidden = state.running;
  };
  const apply = next => { state = next; receivedAt = Date.now(); render(); };
  const sync = async () => {
    if (document.hidden) return;
    try {
      const response = await fetch(`fast_clock.php?session_id=${encodeURIComponent(root.dataset.sessionId)}`, {headers: {'Accept': 'application/json'}, cache: 'no-store'});
      if (response.ok) apply(await response.json());
    } catch (_) {}
  };
  root.querySelectorAll('[data-fast-clock-action]').forEach(button => button.addEventListener('click', async () => {
    const action = button.dataset.fastClockAction;
    button.disabled = true;
    try {
      const body = new URLSearchParams({csrf_token: root.dataset.csrf, session_id: root.dataset.sessionId, action});
      const response = await fetch('fast_clock.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'}, body});
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || 'Unable to update Fast Clock.');
      apply(result);
      const primary = root.querySelector('[data-fast-clock-action]:first-of-type');
      if (primary) {
        primary.dataset.fastClockAction = result.running ? 'pause' : (result.started ? 'resume' : 'start');
        primary.textContent = result.running ? 'Pause' : (result.started ? 'Resume' : 'Start');
      }
      status.textContent = `Fast Clock ${action} complete.`;
    } catch (error) {
      status.textContent = error.message;
      alert(error.message);
    } finally { button.disabled = false; }
  }));
  render();
  window.setInterval(render, 250);
  window.setInterval(sync, 15000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });
})();
