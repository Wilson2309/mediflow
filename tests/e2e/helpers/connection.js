const connectionStates = new WeakMap();

export async function mockConnectionHealth(page, initial = {}) {
  let state = connectionStates.get(page);

  if (state) {
    Object.assign(state, initial);
    return state;
  }

  state = {
    serverReachable: true,
    internetReachable: true,
    ...initial,
  };
  connectionStates.set(page, state);

  await page.route('**/app-health', async (route) => {
    if (!state.serverReachable) {
      await route.abort('failed');
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: { 'Cache-Control': 'no-store' },
      body: JSON.stringify({ ok: true, app: 'MediFlow', timestamp: new Date().toISOString() }),
    });
  });

  await page.route('**/internet-health', async (route) => {
    if (!state.serverReachable) {
      await route.abort('failed');
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      headers: { 'Cache-Control': 'no-store' },
      body: JSON.stringify({ ok: true, internet: state.internetReachable, timestamp: new Date().toISOString() }),
    });
  });

  return state;
}

export async function setMockConnectionHealth(page, nextState) {
  const state = await mockConnectionHealth(page);
  Object.assign(state, nextState);
  return state;
}
