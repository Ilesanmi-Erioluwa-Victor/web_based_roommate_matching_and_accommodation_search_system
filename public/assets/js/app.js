function showLoading(show) {
    document.getElementById('loadingSpinner').classList.toggle('active', show);
}

function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function setLoading(formId, loading) {
    const form = document.getElementById(formId);
    if (!form) return;
    const btn = form.querySelector('button[type="submit"]');
    if (!btn) return;
    if (loading) {
        btn.disabled = true;
        btn.classList.add('btn-loading');
    } else {
        btn.disabled = false;
        btn.classList.remove('btn-loading');
    }
}

function html(strings, ...vals) {
    return strings.reduce((acc, str, i) => acc + str + (vals[i] || ''), '');
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function getAuthUser() {
    const token = localStorage.getItem('token');
    if (!token) return null;
    try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        return payload;
    } catch { return null; }
}

function updateNavbar() {
    const user = getAuthUser();
    const showEls = ['navMatches', 'navConnections', 'navMyListings', 'navProfile'];
    const hideEls = ['navLogin', 'navRegister'];

    showEls.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = user ? 'inline-block' : 'none';
    });
    hideEls.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = user ? 'none' : 'inline-block';
    });

    const adminLink = document.getElementById('navAdmin');
    if (adminLink) adminLink.style.display = user && user.role === 'admin' ? 'inline-block' : 'none';

    const logoutLink = document.getElementById('navLogout');
    if (logoutLink) logoutLink.style.display = user ? 'inline-block' : 'none';
}

async function logout() {
    try { await API.post('/auth/logout'); } catch {}
    API.setToken(null);
    showToast('Logged out.');
    window.location.href = '/';
}

function toggleMobileMenu() {
    document.getElementById('navLinks').classList.toggle('open');
}

function debounce(fn, ms) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), ms);
    };
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.nav-links') && !e.target.closest('.mobile-menu-btn')) {
        document.getElementById('navLinks').classList.remove('open');
    }
});

const page = {
    async home() {
        const user = getAuthUser();
        document.getElementById('pageContent').innerHTML = html`
            <div class="text-center" style="padding:60px 0 0">
                <h1 style="font-size:2.5rem;color:var(--primary);margin-bottom:8px">RoomieMatch</h1>
                <p style="font-size:1.2rem;color:var(--gray);margin-bottom:24px">
                    Find your perfect roommate and accommodation
                </p>
                ${user ? html`
                    <div class="flex gap-4" style="justify-content:center;flex-wrap:wrap">
                        <a href="#" class="btn btn-primary" onclick="router.navigate('/matches')">Find Matches</a>
                        <a href="#" class="btn btn-outline" onclick="router.navigate('/listings')">Browse Listings</a>
                        <a href="#" class="btn btn-secondary" onclick="router.navigate('/profile')">My Profile</a>
                    </div>
                ` : html`
                    <div class="flex gap-4" style="justify-content:center;flex-wrap:wrap">
                        <a href="#" class="btn btn-primary" onclick="router.navigate('/register')">Get Started</a>
                        <a href="#" class="btn btn-outline" onclick="router.navigate('/login')">Login</a>
                        <a href="#" class="btn btn-secondary" onclick="router.navigate('/listings')">Browse Listings</a>
                    </div>
                `}
                <div class="grid grid-3 mt-6" style="max-width:800px;margin:48px auto 0">
                    <div class="card"><div class="card-header">🏠 List Your Room</div>
                    <p style="color:var(--gray);font-size:0.9rem">Post your accommodation and find the perfect roommate who matches your lifestyle.</p></div>
                    <div class="card"><div class="card-header">🔍 Search Listings</div>
                    <p style="color:var(--gray);font-size:0.9rem">Browse available rooms by location, price, amenities and find your next home.</p></div>
                    <div class="card"><div class="card-header">🤝 Smart Matching</div>
                    <p style="color:var(--gray);font-size:0.9rem">Our algorithm matches you with compatible roommates based on lifestyle preferences.</p></div>
                </div>
            </div>
            <h3 class="mt-6 mb-2 text-center" style="margin-top:48px">Recent Listings</h3>
            <div id="homeListings" class="text-center" style="padding:20px;color:var(--gray)">Loading...</div>
        `;
        try {
            const res = await API.get('/listings?limit=6&sort=newest');
            const container = document.getElementById('homeListings');
            if (!res.listings || res.listings.length === 0) {
                container.innerHTML = '<p>No listings yet. Be the first to <a href="#" onclick="router.navigate(\'/listings/create\')">create one</a>.</p>';
                return;
            }
            container.innerHTML = html`
                <div class="grid grid-3" style="margin-top:16px">
                    ${res.listings.map(l => html`
                        <div class="listing-card" style="cursor:pointer" onclick="router.navigate('/listings/${l._id}')">
                            ${l.photos && l.photos[0] ? html`<img src="${l.photos[0].url}" alt="${esc(l.title)}" loading="lazy" style="height:160px">` : html`<div style="height:160px;background:var(--light-gray);display:flex;align-items:center;justify-content:center;color:var(--gray)">No photo</div>`}
                            <div class="listing-card-body">
                                <div class="listing-card-title">${esc(l.title)}</div>
                                <div class="listing-card-price">₦${Number(l.price).toLocaleString()}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <a href="#" class="btn btn-outline mt-4" onclick="router.navigate('/listings')">View All Listings</a>
            `;
        } catch (err) {
            document.getElementById('homeListings').innerHTML = '<p>Could not load listings.</p>';
        }
    },

    async login() {
        document.getElementById('pageContent').innerHTML = html`
            <div style="max-width:400px;margin:60px auto">
                <div class="card">
                    <div class="card-header text-center">Login</div>
                    <form id="loginForm">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" id="loginEmail" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" id="loginPassword" required>
                        </div>
                        <div id="loginError" class="alert alert-error" style="display:none"></div>
                        <button type="submit" class="btn btn-primary btn-block">Login</button>
                    </form>
                    <p class="mt-4 text-center" style="font-size:0.9rem">
                        No account? <a href="#" onclick="router.navigate('/register')">Register</a>
                    </p>
                </div>
            </div>
        `;
        document.getElementById('loginForm').onsubmit = async (e) => {
            e.preventDefault();
            setLoading('loginForm', true);
            document.getElementById('loginError').style.display = 'none';
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            try {
                const res = await API.post('/auth/login', { email, password });
                API.setToken(res.token);
                showToast('Login successful!');
                router.navigate('/');
            } catch (err) {
                document.getElementById('loginError').textContent = err.message;
                document.getElementById('loginError').style.display = 'block';
                setLoading('loginForm', false);
            }
        };
    },

    async register() {
        document.getElementById('pageContent').innerHTML = html`
            <div style="max-width:500px;margin:40px auto">
                <div class="card">
                    <div class="card-header text-center">Create Account</div>
                    <form id="registerForm">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" id="regName" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" id="regEmail" required>
                        </div>
                        <div class="form-group">
                            <label>Phone (optional)</label>
                            <input type="tel" class="form-control" id="regPhone">
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" class="form-control" id="regPassword" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>I am a</label>
                            <select class="form-control" id="regRole">
                                <option value="both">Both (looking for room &amp; offering room)</option>
                                <option value="seeker">Seeker (looking for a room)</option>
                                <option value="lister">Lister (offering a room)</option>
                            </select>
                        </div>
                        <div id="regError" class="alert alert-error" style="display:none"></div>
                        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    </form>
                    <p class="mt-4 text-center" style="font-size:0.9rem">
                        Already have an account? <a href="#" onclick="router.navigate('/login')">Login</a>
                    </p>
                </div>
            </div>
        `;
        document.getElementById('registerForm').onsubmit = async (e) => {
            e.preventDefault();
            setLoading('registerForm', true);
            document.getElementById('regError').style.display = 'none';
            const data = {
                name: document.getElementById('regName').value,
                email: document.getElementById('regEmail').value,
                phone: document.getElementById('regPhone').value,
                password: document.getElementById('regPassword').value,
                role: document.getElementById('regRole').value,
            };
            try {
                const res = await API.post('/auth/register', data);
                API.setToken(res.token);
                showToast('Account created! Check your email to verify.');
                router.navigate('/');
            } catch (err) {
                document.getElementById('regError').textContent = err.message;
                document.getElementById('regError').style.display = 'block';
                setLoading('registerForm', false);
            }
        };
    },

    async listings() {
        const params = new URLSearchParams(location.search);
        const page = parseInt(params.get('page')) || 1;
        const q = params.get('q') || '';
        const priceMin = params.get('priceMin') || '';
        const priceMax = params.get('priceMax') || '';
        const roomType = params.get('roomType') || '';
        const radius = params.get('radius') || '';

        document.getElementById('pageContent').innerHTML = html`
            <h2 style="margin-bottom:16px">Accommodation Listings</h2>
            <div class="card mb-4">
                <form id="searchForm" class="flex flex-wrap gap-2 items-center">
                    <input type="text" class="form-control" id="searchText" placeholder="Search listings..." value="${esc(q)}" style="max-width:250px">
                    <input type="number" class="form-control" id="searchPriceMin" placeholder="Min price" value="${priceMin}" style="max-width:120px">
                    <input type="number" class="form-control" id="searchPriceMax" placeholder="Max price" value="${priceMax}" style="max-width:120px">
                    <select class="form-control" id="searchRoomType" style="max-width:160px">
                        <option value="">All types</option>
                        <option value="self_contain" ${roomType==='self_contain'?'selected':''}>Self Contain</option>
                        <option value="shared_room" ${roomType==='shared_room'?'selected':''}>Shared Room</option>
                        <option value="whole_apartment" ${roomType==='whole_apartment'?'selected':''}>Whole Apartment</option>
                        <option value="studio" ${roomType==='studio'?'selected':''}>Studio</option>
                    </select>
                    <input type="number" class="form-control" id="searchRadius" placeholder="Radius (km)" value="${radius}" style="max-width:120px">
                    <button type="submit" class="btn btn-primary btn-sm">Search</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="router.navigate('/listings')">Reset</button>
                    ${getAuthUser() ? html`<button type="button" class="btn btn-success btn-sm" onclick="router.navigate('/listings/create')">+ New Listing</button>` : ''}
                </form>
            </div>
            <div id="listingsContainer"><div class="text-center" style="padding:40px;color:var(--gray)">Loading...</div></div>
            <div id="listingsPagination" class="pagination"></div>
        `;

        document.getElementById('searchForm').onsubmit = (e) => {
            e.preventDefault();
            this._searchListings();
        };

        const liveSearch = debounce(() => this._searchListings(), 300);
        ['searchText', 'searchPriceMin', 'searchPriceMax', 'searchRadius'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.oninput = liveSearch;
        });
        const roomTypeEl = document.getElementById('searchRoomType');
        if (roomTypeEl) roomTypeEl.onchange = liveSearch;

        await this._loadListings({ page, q, priceMin, priceMax, roomType, radius });
    },

    _searchListings() {
        const t = document.getElementById('searchText').value;
        const pmin = document.getElementById('searchPriceMin').value;
        const pmax = document.getElementById('searchPriceMax').value;
        const rt = document.getElementById('searchRoomType').value;
        const rad = document.getElementById('searchRadius').value;
        const p = new URLSearchParams();
        if (t) p.set('q', t);
        if (pmin) p.set('priceMin', pmin);
        if (pmax) p.set('priceMax', pmax);
        if (rt) p.set('roomType', rt);
        if (rad) p.set('radius', rad);
        const qs = p.toString();
        router.navigate(`/listings${qs ? '?' + qs : ''}`);
    },

    async _loadListings(filters) {
        const p = new URLSearchParams();
        p.set('page', filters.page || 1);
        if (filters.q) p.set('text', filters.q);
        if (filters.priceMin) p.set('priceMin', filters.priceMin);
        if (filters.priceMax) p.set('priceMax', filters.priceMax);
        if (filters.roomType) p.set('roomType', filters.roomType);
        if (filters.radius) p.set('radius', filters.radius);
        if (filters.lat && filters.lng) { p.set('lat', filters.lat); p.set('lng', filters.lng); }

        try {
            const res = await API.get(`/listings?${p.toString()}`);
            const container = document.getElementById('listingsContainer');
            const pagination = document.getElementById('listingsPagination');

            if (!res.listings || res.listings.length === 0) {
                const suggestion = res.suggestion || 'No listings found.';
                container.innerHTML = html`
                    <div class="empty-state">
                        <h3>No Listings Found</h3>
                        <p>${esc(suggestion)}</p>
                        <button class="btn btn-primary" onclick="router.navigate('/listings/create')">Create a Listing</button>
                    </div>
                `;
                pagination.innerHTML = '';
                return;
            }

            container.innerHTML = html`
                <div class="grid grid-3">
                    ${res.listings.map(l => html`
                        <div class="listing-card">
                            ${l.photos && l.photos[0] ? html`<img src="${l.photos[0].url}" alt="${esc(l.title)}" loading="lazy">` : html`<div style="height:200px;background:var(--light-gray);display:flex;align-items:center;justify-content:center;color:var(--gray)">No photo</div>`}
                            <div class="listing-card-body">
                                <div class="listing-card-title">${esc(l.title)}</div>
                                <div class="listing-card-price">₦${Number(l.price).toLocaleString()}</div>
                                <div class="listing-card-meta">
                                    ${l.roomType ? esc(l.roomType.replace('_',' ')) : ''}
                                    ${l.address?.city ? '&middot; ' + esc(l.address.city) : ''}
                                </div>
                                <div class="listing-card-amenities">
                                    ${(l.amenities || []).slice(0,4).map(a => html`<span class="tag">${esc(a)}</span>`).join('')}
                                    ${l.amenities && l.amenities.length > 4 ? html`<span class="tag">+${l.amenities.length - 4}</span>` : ''}
                                </div>
                                <button class="btn btn-primary btn-sm btn-block mt-2" onclick="router.navigate('/listings/${l._id}')">View Details</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;

            pagination.innerHTML = '';
            if (res.pages > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.textContent = '«';
                prevBtn.disabled = res.page <= 1;
                prevBtn.onclick = () => {
                    const p = new URLSearchParams(location.search);
                    p.set('page', res.page - 1);
                    router.navigate(`/listings?${p.toString()}`);
                };
                pagination.appendChild(prevBtn);

                let start = Math.max(1, res.page - 2);
                let end = Math.min(res.pages, res.page + 2);
                if (start > 1) {
                    const first = document.createElement('button');
                    first.textContent = '1';
                    first.onclick = () => { const p = new URLSearchParams(location.search); p.set('page', 1); router.navigate(`/listings?${p.toString()}`); };
                    pagination.appendChild(first);
                    if (start > 2) { const dots = document.createElement('span'); dots.textContent = '...'; dots.className = 'pagination-dots'; pagination.appendChild(dots); }
                }
                for (let i = start; i <= end; i++) {
                    const btn = document.createElement('button');
                    btn.textContent = i;
                    if (i === res.page) btn.className = 'active';
                    btn.onclick = () => {
                        const p = new URLSearchParams(location.search);
                        p.set('page', i);
                        router.navigate(`/listings?${p.toString()}`);
                    };
                    pagination.appendChild(btn);
                }
                if (end < res.pages) {
                    if (end < res.pages - 1) { const dots = document.createElement('span'); dots.textContent = '...'; dots.className = 'pagination-dots'; pagination.appendChild(dots); }
                    const last = document.createElement('button');
                    last.textContent = res.pages;
                    last.onclick = () => { const p = new URLSearchParams(location.search); p.set('page', res.pages); router.navigate(`/listings?${p.toString()}`); };
                    pagination.appendChild(last);
                }

                const nextBtn = document.createElement('button');
                nextBtn.textContent = '»';
                nextBtn.disabled = res.page >= res.pages;
                nextBtn.onclick = () => {
                    const p = new URLSearchParams(location.search);
                    p.set('page', res.page + 1);
                    router.navigate(`/listings?${p.toString()}`);
                };
                pagination.appendChild(nextBtn);
            }
        } catch (err) {
            document.getElementById('listingsContainer').innerHTML = html`
                <div class="empty-state"><h3>Error</h3><p>${esc(err.message)}</p></div>
            `;
        }
    },

    async listingDetail(id) {
        showLoading(true);
        try {
            const res = await API.get(`/listings/${id}`);
            const l = res.listing;
            document.getElementById('pageContent').innerHTML = html`
                <div style="max-width:800px;margin:0 auto">
                    <button class="btn btn-secondary btn-sm mb-4" onclick="router.navigate('/listings')">← Back to Listings</button>
                    ${l.photos && l.photos.length > 0 ? html`
                        <div class="card mb-4" style="overflow:hidden;padding:0">
                            <div style="display:flex;overflow-x:auto;gap:4px">
                                ${l.photos.map(p => html`<img src="${p.url}" style="width:100%;max-height:400px;object-fit:cover;flex-shrink:0" alt="">`).join('')}
                            </div>
                        </div>
                    ` : ''}
                    <div class="card mb-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2>${esc(l.title)}</h2>
                                <div class="listing-card-price">₦${Number(l.price).toLocaleString()} / ${l.pricePeriod || 'monthly'}</div>
                            </div>
                            ${getAuthUser() ? html`
                                <button class="btn btn-outline btn-sm" onclick="toggleFavorite('${id}')">
                                    ${(getAuthUser() && res.isFavorite) ? '❤️ Saved' : '♡ Save'}
                                </button>
                            ` : ''}
                        </div>
                        <p class="mt-4">${esc(l.description || 'No description')}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            ${(l.amenities || []).map(a => html`<span class="tag">${esc(a)}</span>`).join('')}
                        </div>
                        <div class="mt-4" style="color:var(--gray);font-size:0.9rem">
                            <div><strong>Type:</strong> ${esc((l.roomType || '').replace('_',' '))}</div>
                            <div><strong>Location:</strong> ${l.address ? [l.address.fullAddress, l.address.area, l.address.city, l.address.state].filter(Boolean).join(', ') : 'Not specified'}</div>
                            <div><strong>Available from:</strong> ${l.availableFrom ? new Date(l.availableFrom).toLocaleDateString() : 'Immediately'}</div>
                            <div><strong>Views:</strong> ${l.viewsCount || 0}</div>
                        </div>
                    </div>
                    ${getAuthUser() ? html`
                        <div class="flex gap-4">
                            <button class="btn btn-primary" onclick="checkListingCompatibility('${id}')">View My Compatibility</button>
                            <button class="btn btn-success" onclick="sendConnectionFromListing('${id}')">Send Connection Request</button>
                        </div>
                        <div id="compatibilityResult" class="mt-4"></div>
                    ` : html`
                        <div class="alert alert-info">Login to see your compatibility score and send connection requests.</div>
                    `}
                </div>
            `;
        } catch (err) {
            document.getElementById('pageContent').innerHTML = html`
                <div class="empty-state"><h3>Not Found</h3><p>${esc(err.message)}</p></div>
            `;
        } finally { showLoading(false); }
    },

    async createListing() {
        if (!getAuthUser()) { router.navigate('/login'); return; }
        document.getElementById('pageContent').innerHTML = html`
            <div style="max-width:600px;margin:0 auto">
                <div class="card">
                    <div class="card-header">Create New Listing</div>
                    <form id="createListingForm">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" class="form-control" id="listingTitle" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea class="form-control" id="listingDescription"></textarea>
                        </div>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label>Price (₦) *</label>
                                <input type="number" class="form-control" id="listingPrice" required>
                            </div>
                            <div class="form-group">
                                <label>Period</label>
                                <select class="form-control" id="listingPricePeriod">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Room Type</label>
                            <select class="form-control" id="listingRoomType">
                                <option value="shared_room">Shared Room</option>
                                <option value="self_contain">Self Contain</option>
                                <option value="studio">Studio</option>
                                <option value="whole_apartment">Whole Apartment</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Roommates Needed</label>
                            <input type="number" class="form-control" id="listingRoommatesNeeded" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label>Full Address</label>
                            <input type="text" class="form-control" id="listingAddress" placeholder="e.g. 123, Main Street">
                        </div>
                        <div class="grid grid-2">
                            <div class="form-group">
                                <label>Area</label>
                                <input type="text" class="form-control" id="listingArea" placeholder="e.g. Sango">
                            </div>
                            <div class="form-group">
                                <label>City</label>
                                <input type="text" class="form-control" id="listingCity">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" class="form-control" id="listingState">
                        </div>
                        <div class="form-group">
                            <label>Amenities (comma separated)</label>
                            <input type="text" class="form-control" id="listingAmenities" placeholder="e.g. wifi, water_supply, generator, furnished">
                        </div>
                        <div class="form-group">
                            <label>Photos (optional)</label>
                            <input type="file" class="form-control" id="listingPhotos" name="photos[]" accept="image/*" multiple>
                            <small style="color:var(--gray)">Max 8 photos. You can also add photos later.</small>
                        </div>
                        <div id="listingPhotoPreviews" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"></div>
                        <div id="listingError" class="alert alert-error" style="display:none"></div>
                        <button type="submit" class="btn btn-primary btn-block">Create Listing</button>
                    </form>
                </div>
            </div>
        `;
        document.getElementById('createListingForm').onsubmit = async (e) => {
            e.preventDefault();
            setLoading('createListingForm', true);
            document.getElementById('listingError').style.display = 'none';
            const amenities = document.getElementById('listingAmenities').value.split(',').map(s => s.trim()).filter(Boolean);
            const fd = new FormData();
            fd.append('title', document.getElementById('listingTitle').value);
            fd.append('description', document.getElementById('listingDescription').value);
            fd.append('price', document.getElementById('listingPrice').value);
            fd.append('pricePeriod', document.getElementById('listingPricePeriod').value);
            fd.append('roomType', document.getElementById('listingRoomType').value);
            fd.append('totalRoommatesNeeded', document.getElementById('listingRoommatesNeeded').value);
            fd.append('address[fullAddress]', document.getElementById('listingAddress').value);
            fd.append('address[area]', document.getElementById('listingArea').value);
            fd.append('address[city]', document.getElementById('listingCity').value);
            fd.append('address[state]', document.getElementById('listingState').value);
            amenities.forEach(a => fd.append('amenities[]', a));
            const photoInput = document.getElementById('listingPhotos');
            for (const file of photoInput.files) {
                fd.append('photos[]', file);
            }
            try {
                const res = await API.upload('POST', '/listings', fd);
                showToast('Listing created!');
                router.navigate('/my-listings');
            } catch (err) {
                document.getElementById('listingError').textContent = err.message;
                document.getElementById('listingError').style.display = 'block';
                setLoading('createListingForm', false);
            }
        };
        document.getElementById('listingPhotos').onchange = function() {
            const container = document.getElementById('listingPhotoPreviews');
            container.innerHTML = '';
            for (const file of this.files) {
                const img = document.createElement('img');
                img.style.width = '80px'; img.style.height = '80px';
                img.style.objectFit = 'cover'; img.style.borderRadius = '8px';
                img.src = URL.createObjectURL(file);
                container.appendChild(img);
            }
        };
    },

    async myListings() {
        if (!getAuthUser()) { router.navigate('/login'); return; }
        document.getElementById('pageContent').innerHTML = html`<h2 class="mb-4">My Listings</h2><div id="myListingsContainer"><div class="text-center" style="padding:40px;color:var(--gray)">Loading...</div></div>`;
        try {
            const res = await API.get('/users/me/listings');
            const container = document.getElementById('myListingsContainer');
            if (!res.listings || res.listings.length === 0) {
                container.innerHTML = html`
                    <div class="empty-state">
                        <h3>No Listings Yet</h3>
                        <p>Create your first accommodation listing.</p>
                        <button class="btn btn-primary" onclick="router.navigate('/listings/create')">Create Listing</button>
                    </div>`;
                return;
            }
            container.innerHTML = html`
                <div class="grid grid-2">
                    ${res.listings.map(l => html`
                        <div class="card">
                            <div class="flex justify-between items-center">
                                <h3>${esc(l.title)}</h3>
                                <span class="badge badge-${l.status === 'active' ? 'success' : 'warning'}">${l.status}</span>
                            </div>
                            <div style="color:var(--primary);font-weight:700;margin:8px 0">₦${Number(l.price).toLocaleString()}</div>
                            <div style="color:var(--gray);font-size:0.85rem">${l.viewsCount || 0} views</div>
                            <div class="flex gap-2 mt-2">
                                <button class="btn btn-primary btn-sm" onclick="router.navigate('/listings/${l._id}')">View</button>
                                <button class="btn btn-secondary btn-sm" onclick="editListing('${l._id}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteListing('${l._id}')">Delete</button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } catch (err) {
            document.getElementById('myListingsContainer').innerHTML = html`<div class="empty-state"><h3>Error</h3><p>${esc(err.message)}</p></div>`;
        }
    },

    async matches() {
        if (!getAuthUser()) { router.navigate('/login'); return; }
        document.getElementById('pageContent').innerHTML = html`
            <h2 class="mb-4">Compatible Roommates</h2>
            <div class="card mb-4">
                <p style="color:var(--gray);font-size:0.9rem">
                    Ranked by your lifestyle compatibility. Complete your lifestyle profile for better matches.
                </p>
                <button class="btn btn-secondary btn-sm mt-2" onclick="router.navigate('/profile')">Update Lifestyle Profile</button>
            </div>
            <div id="matchesContainer"><div class="text-center" style="padding:40px;color:var(--gray)">Computing compatibility scores...</div></div>
        `;
        try {
            const res = await API.get('/matches/roommates');
            const container = document.getElementById('matchesContainer');

            if (!res.matches || res.matches.length === 0) {
                container.innerHTML = html`
                    <div class="empty-state">
                        <h3>No Matches Found</h3>
                        <p>Complete your lifestyle profile and make sure you are set to "actively looking" to find matches.</p>
                        <button class="btn btn-primary" onclick="router.navigate('/profile')">Update Profile</button>
                    </div>`;
                return;
            }

            container.innerHTML = html`
                <div style="display:grid;gap:12px">
                    ${res.matches.map(m => {
                        const compat = m.compatibility;
                        const scoreColor = compat.score >= 80 ? 'var(--secondary)' : compat.score >= 60 ? 'var(--warning)' : 'var(--gray)';
                        return html`
                            <div class="match-card">
                                <div class="match-score" style="color:${scoreColor}">${compat.passedDealBreakers ? compat.score + '%' : '✗'}</div>
                                <img src="${m.user.profilePhotoUrl || '/assets/images/default-avatar.png'}" alt="" class="avatar">
                                <div class="match-info">
                                    <div class="match-name">${esc(m.user.name)}</div>
                                    <div class="match-details">${esc(m.user.gender || '')} ${m.user.lifestyle?.cleanliness ? '· Cleanliness: ' + m.user.lifestyle.cleanliness + '/5' : ''}</div>
                                    ${compat.isPartial ? html`<div class="completeness-warning mt-2"><strong>Partial match</strong> &mdash; profile incomplete</div>` : ''}
                                    ${!compat.passedDealBreakers ? html`<div class="completeness-warning mt-2"><strong>Dealbreakers:</strong> ${compat.dealBreakerFailures.join(', ')}</div>` : ''}
                                    ${compat.passedDealBreakers && compat.categoryScores ? html`
                                        <details style="font-size:0.8rem;color:var(--gray);margin-top:4px">
                                            <summary>Breakdown</summary>
                                            ${Object.entries(compat.categoryScores).filter(([_,v]) => v !== null).map(([k,v]) => html`<div>${esc(k)}: ${Math.round(v*100)}%</div>`).join('')}
                                        </details>
                                    ` : ''}
                                </div>
                                <div class="match-actions">
                                    <button class="btn btn-primary btn-sm" data-userid="${m.user._id}" onclick="sendConnection(this)">Connect</button>
                                    <button class="btn btn-secondary btn-sm" onclick="router.navigate('/users/${m.user._id}')">View</button>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        } catch (err) {
            document.getElementById('matchesContainer').innerHTML = html`<div class="empty-state"><h3>Error</h3><p>${esc(err.message)}</p></div>`;
        }
    },

    async profile() {
        if (!getAuthUser()) { router.navigate('/login'); return; }
        document.getElementById('pageContent').innerHTML = html`
            <div style="max-width:700px;margin:0 auto">
                <div class="card mb-4">
                    <div class="card-header">Profile</div>
                    <div id="profileInfo"><div class="text-center" style="padding:20px;color:var(--gray)">Loading...</div></div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">Lifestyle Profile</div>
                    <p style="color:var(--gray);font-size:0.85rem;margin-bottom:16px">
                        Fill this out to get accurate roommate matches. The more complete this is, the better your compatibility scores.
                    </p>
                    <div id="lifestyleForm"><div class="text-center" style="padding:20px;color:var(--gray)">Loading...</div></div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">Deal Breakers</div>
                    <div id="dealBreakersForm"><div class="text-center" style="padding:20px;color:var(--gray)">Loading...</div></div>
                </div>
                <div class="card">
                    <div class="card-header">Matching Status</div>
                    <div id="matchingStatusForm"><div class="text-center" style="padding:20px;color:var(--gray)">Loading...</div></div>
                </div>
            </div>
        `;

        try {
            const res = await API.get('/users/me');
            const user = res.user;

            document.getElementById('profileInfo').innerHTML = html`
                <div class="flex gap-4 items-center mb-4">
                    <div>
                        ${user.profilePhotoUrl ? html`<img src="${user.profilePhotoUrl}" class="avatar avatar-lg">` : html`<div class="avatar avatar-lg" style="display:flex;align-items:center;justify-content:center;font-size:2rem;color:var(--gray)">${esc(user.name[0])}</div>`}
                    </div>
                    <div style="flex:1">
                        <h3>${esc(user.name)}</h3>
                        <p style="color:var(--gray);font-size:0.9rem">${esc(user.email)}</p>
                        <span class="badge badge-${user.isEmailVerified ? 'success' : 'warning'}">${user.isEmailVerified ? 'Verified' : 'Unverified'}</span>
                        <span class="badge badge-${user.matchingStatus === 'actively_looking' ? 'success' : 'warning'}">${esc(user.matchingStatus.replace('_',' '))}</span>
                    </div>
                    <div>
                        <form id="photoForm">
                            <label class="btn btn-secondary btn-sm" style="cursor:pointer">
                                Change Photo
                                <input type="file" name="photo" accept="image/*" style="display:none" onchange="selectProfilePhoto(this)">
                            </label>
                        </form>
                    </div>
                </div>
                <div class="flex gap-4">
                    <div class="form-group" style="flex:1">
                        <label>Phone</label>
                        <input type="tel" class="form-control" id="profilePhone" value="${esc(user.phone || '')}">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Gender</label>
                        <select class="form-control" id="profileGender">
                            <option value="">Not specified</option>
                            <option value="male" ${user.gender === 'male' ? 'selected' : ''}>Male</option>
                            <option value="female" ${user.gender === 'female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="updateProfile()">Update Profile</button>
                <button class="btn btn-danger mt-2" onclick="logout()" style="width:100%">Logout</button>
            `;

            const ls = user.lifestyle || {};
            const comp = calculateCompleteness(ls);
            const completionPercent = Math.round(comp * 100);

            document.getElementById('lifestyleForm').innerHTML = html`
                ${comp < 0.7 ? html`
                    <div class="completeness-warning mb-4">
                        <strong>Profile is ${completionPercent}% complete.</strong> Fill in more fields to get accurate roommate matches.
                    </div>
                ` : html`
                    <div class="alert alert-success mb-4">Profile is ${completionPercent}% complete. ✓</div>
                `}
                <div class="progress-bar mb-4">
                    <div class="progress-bar-fill" style="width:${completionPercent}%"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Budget Min (₦)</label>
                        <input type="number" class="form-control" id="lsBudgetMin" value="${ls.budgetMin || ''}">
                    </div>
                    <div class="form-group">
                        <label>Budget Max (₦)</label>
                        <input type="number" class="form-control" id="lsBudgetMax" value="${ls.budgetMax || ''}">
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Cleanliness (1-5)</label>
                        <select class="form-control" id="lsCleanliness">
                            <option value="">Not set</option>
                            ${[1,2,3,4,5].map(n => html`<option value="${n}" ${ls.cleanliness == n ? 'selected' : ''}>${n}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sleep Schedule</label>
                        <select class="form-control" id="lsSleepSchedule">
                            <option value="">Not set</option>
                            <option value="early_bird" ${ls.sleepSchedule === 'early_bird' ? 'selected' : ''}>Early Bird</option>
                            <option value="night_owl" ${ls.sleepSchedule === 'night_owl' ? 'selected' : ''}>Night Owl</option>
                            <option value="flexible" ${ls.sleepSchedule === 'flexible' ? 'selected' : ''}>Flexible</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Noise Tolerance (1-5)</label>
                        <select class="form-control" id="lsNoiseLevel">
                            <option value="">Not set</option>
                            ${[1,2,3,4,5].map(n => html`<option value="${n}" ${ls.noiseLevel == n ? 'selected' : ''}>${n}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Work Schedule</label>
                        <select class="form-control" id="lsWorkSchedule">
                            <option value="">Not set</option>
                            <option value="9to5" ${ls.workSchedule === '9to5' ? 'selected' : ''}>9 to 5</option>
                            <option value="night_shift" ${ls.workSchedule === 'night_shift' ? 'selected' : ''}>Night Shift</option>
                            <option value="student" ${ls.workSchedule === 'student' ? 'selected' : ''}>Student</option>
                            <option value="remote" ${ls.workSchedule === 'remote' ? 'selected' : ''}>Remote</option>
                            <option value="mixed" ${ls.workSchedule === 'mixed' ? 'selected' : ''}>Mixed</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Guest Frequency</label>
                        <select class="form-control" id="lsGuestFrequency">
                            <option value="">Not set</option>
                            <option value="rarely" ${ls.guestFrequency === 'rarely' ? 'selected' : ''}>Rarely</option>
                            <option value="sometimes" ${ls.guestFrequency === 'sometimes' ? 'selected' : ''}>Sometimes</option>
                            <option value="often" ${ls.guestFrequency === 'often' ? 'selected' : ''}>Often</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender Preference</label>
                        <select class="form-control" id="lsGenderPreference">
                            <option value="any" ${ls.genderPreference === 'any' ? 'selected' : ''}>Any</option>
                            <option value="same" ${ls.genderPreference === 'same' ? 'selected' : ''}>Same Gender</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label><input type="checkbox" id="lsSmoker" ${ls.smoker ? 'checked' : ''}> I smoke</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="lsToleratesSmoking" ${ls.toleratesSmoking ? 'checked' : ''}> I tolerate smoking</label>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label><input type="checkbox" id="lsHasPets" ${ls.hasPets ? 'checked' : ''}> I have pets</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" id="lsToleratesPets" ${ls.toleratesPets ? 'checked' : ''}> I tolerate pets</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Preferred Locations (comma separated)</label>
                    <input type="text" class="form-control" id="lsPreferredLocations" value="${(ls.preferredLocations || []).join(', ')}">
                </div>
                <div class="form-group">
                    <label>Additional Notes</label>
                    <textarea class="form-control" id="lsAdditionalNotes">${esc(ls.additionalNotes || '')}</textarea>
                </div>
                <button class="btn btn-primary" onclick="updateLifestyle()">Save Lifestyle Profile</button>
            `;

            const db = user.dealBreakers || {};
            document.getElementById('dealBreakersForm').innerHTML = html`
                <div class="alert alert-warning mb-4">
                    Deal breakers are <strong>hard filters</strong>. Users who violate your deal breakers will be excluded from your match results entirely.
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="dbNoSmokers" ${db.noSmokers ? 'checked' : ''}> No smokers (exclude smokers from my matches)</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="dbNoPets" ${db.noPets ? 'checked' : ''}> No pets (exclude pet owners from my matches)</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="dbSameGenderOnly" ${db.sameGenderOnly ? 'checked' : ''}> Same gender only</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="dbMaxBudgetStrict" ${db.maxBudgetStrict ? 'checked' : ''}> Strict budget (no overlap = excluded)</label>
                </div>
                <button class="btn btn-primary" onclick="updateDealBreakers()">Save Deal Breakers</button>
            `;

            document.getElementById('matchingStatusForm').innerHTML = html`
                <div class="form-group">
                    <label>Set your matching status</label>
                    <select class="form-control" id="matchingStatus">
                        <option value="actively_looking" ${user.matchingStatus === 'actively_looking' ? 'selected' : ''}>Actively looking</option>
                        <option value="paused" ${user.matchingStatus === 'paused' ? 'selected' : ''}>Paused</option>
                        <option value="found_roommate" ${user.matchingStatus === 'found_roommate' ? 'selected' : ''}>Found roommate</option>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="updateMatchingStatus()">Update Status</button>
            `;

        } catch (err) {
            document.getElementById('profileInfo').innerHTML = html`<div class="alert alert-error">${esc(err.message)}</div>`;
        }
    },

    async connections() {
        if (!getAuthUser()) { router.navigate('/login'); return; }
        document.getElementById('pageContent').innerHTML = html`
            <h2 class="mb-4">Connections</h2>
            <div class="mb-4">
                <button class="btn btn-primary btn-sm" onclick="showPendingConnections()">Pending Requests</button>
                <button class="btn btn-secondary btn-sm" onclick="showAcceptedConnections()">Active Connections</button>
            </div>
            <div id="connectionsContainer"><div class="text-center" style="padding:40px;color:var(--gray)">Loading...</div></div>
        `;
        showPendingConnections();
    },
};

function calculateCompleteness(ls) {
    const fields = ['budgetMin','budgetMax','cleanliness','sleepSchedule','smoker','toleratesSmoking','hasPets','toleratesPets','noiseLevel','guestFrequency','workSchedule'];
    let filled = 0;
    fields.forEach(f => {
        const v = ls[f];
        if (v !== null && v !== undefined && v !== '' && !(Array.isArray(v) && v.length === 0)) filled++;
    });
    return filled / fields.length;
}

let pendingPhoto = null;

function selectProfilePhoto(input) {
    if (!input.files[0]) return;
    pendingPhoto = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const avatarContainer = document.querySelector('#profileInfo > .flex > div:first-child');
        if (avatarContainer) {
            avatarContainer.innerHTML = `<img src="${e.target.result}" class="avatar avatar-lg" style="object-fit:cover">`;
        }
    };
    reader.readAsDataURL(input.files[0]);
    showToast('Photo selected. Click "Update Profile" to save.');
}

async function uploadProfilePhoto(input) {
    if (!input.files[0]) return;
    const fd = new FormData();
    fd.append('photo', input.files[0]);
    try {
        const res = await API.upload('POST', '/users/me/profile-photo', fd);
        showToast('Photo updated!');
        router.navigate('/profile');
    } catch (err) { showToast(err.message, 'error'); }
}

async function updateProfile() {
    const btn = document.querySelector('button[onclick="updateProfile()"]');
    if (btn) { btn.disabled = true; btn.classList.add('btn-loading'); }
    try {
        if (pendingPhoto) {
            const fd = new FormData();
            fd.append('photo', pendingPhoto);
            await API.upload('POST', '/users/me/profile-photo', fd);
            pendingPhoto = null;
        }
        await API.patch('/users/me/profile', {
            phone: document.getElementById('profilePhone').value,
            gender: document.getElementById('profileGender').value,
        });
        showToast('Profile updated!');
    } catch (err) { showToast(err.message, 'error'); }
    if (btn) { btn.disabled = false; btn.classList.remove('btn-loading'); }
}

async function updateLifestyle() {
    const btn = document.querySelector('button[onclick="updateLifestyle()"]');
    if (btn) { btn.disabled = true; btn.classList.add('btn-loading'); }
    const getVal = (id) => document.getElementById(id)?.value;
    const getBool = (id) => document.getElementById(id)?.checked;
    try {
        await API.patch('/users/me/lifestyle', {
            budgetMin: getVal('lsBudgetMin') ? parseFloat(getVal('lsBudgetMin')) : null,
            budgetMax: getVal('lsBudgetMax') ? parseFloat(getVal('lsBudgetMax')) : null,
            cleanliness: getVal('lsCleanliness') ? parseInt(getVal('lsCleanliness')) : null,
            sleepSchedule: getVal('lsSleepSchedule') || null,
            noiseLevel: getVal('lsNoiseLevel') ? parseInt(getVal('lsNoiseLevel')) : null,
            workSchedule: getVal('lsWorkSchedule') || null,
            guestFrequency: getVal('lsGuestFrequency') || null,
            genderPreference: getVal('lsGenderPreference') || 'any',
            smoker: getBool('lsSmoker'),
            toleratesSmoking: getBool('lsToleratesSmoking'),
            hasPets: getBool('lsHasPets'),
            toleratesPets: getBool('lsToleratesPets'),
            preferredLocations: getVal('lsPreferredLocations') ? getVal('lsPreferredLocations').split(',').map(s => s.trim()).filter(Boolean) : [],
            additionalNotes: document.getElementById('lsAdditionalNotes')?.value || '',
        });
        showToast('Lifestyle profile saved!');
        router.navigate('/profile');
    } catch (err) { showToast(err.message, 'error'); }
    if (btn) { btn.disabled = false; btn.classList.remove('btn-loading'); }
}

async function updateDealBreakers() {
    const btn = document.querySelector('button[onclick="updateDealBreakers()"]');
    if (btn) { btn.disabled = true; btn.classList.add('btn-loading'); }
    const getBool = (id) => document.getElementById(id)?.checked;
    try {
        await API.patch('/users/me/lifestyle', {
            dealBreakers: {
                noSmokers: getBool('dbNoSmokers'),
                noPets: getBool('dbNoPets'),
                sameGenderOnly: getBool('dbSameGenderOnly'),
                maxBudgetStrict: getBool('dbMaxBudgetStrict'),
            }
        });
        showToast('Deal breakers saved!');
    } catch (err) { showToast(err.message, 'error'); }
    if (btn) { btn.disabled = false; btn.classList.remove('btn-loading'); }
}

async function updateMatchingStatus() {
    const btn = document.querySelector('button[onclick="updateMatchingStatus()"]');
    if (btn) { btn.disabled = true; btn.classList.add('btn-loading'); }
    try {
        const status = document.getElementById('matchingStatus').value;
        await API.patch('/users/me/matching-status', { status });
        showToast('Status updated!');
        router.navigate('/profile');
    } catch (err) { showToast(err.message, 'error'); }
    if (btn) { btn.disabled = false; btn.classList.remove('btn-loading'); }
}

async function sendConnection(btn) {
    const userId = btn.dataset.userid;
    const listingId = btn.dataset.listingid || null;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    try {
        const data = { recipientId: userId };
        if (listingId) data.listingId = listingId;
        await API.post('/connections', data);
        btn.textContent = 'Request Sent';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-secondary');
        showToast('Connection request sent!');
    } catch (err) {
        btn.disabled = false;
        btn.textContent = 'Connect';
        showToast(err.message, 'error');
    }
}

async function sendConnectionFromListing(listingId) {
    if (!getAuthUser()) { router.navigate('/login'); return; }
    try {
        const res = await API.get(`/listings/${listingId}`);
        const listerId = res.listing.lister;
        const btn = document.querySelector(`[data-listid="${listingId}"]`);
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }
        await API.post('/connections', { recipientId: listerId, listingId });
        showToast('Connection request sent!');
        if (btn) { btn.textContent = 'Request Sent'; btn.classList.remove('btn-primary'); btn.classList.add('btn-secondary'); }
    } catch (err) { showToast(err.message, 'error'); }
}

async function checkListingCompatibility(listingId) {
    if (!getAuthUser()) { router.navigate('/login'); return; }
    try {
        const res = await API.get(`/listings/${listingId}/compatibility`);
        const container = document.getElementById('compatibilityResult');
        if (res.type === 'multi_occupant') {
            container.innerHTML = html`
                <div class="card mt-4">
                    <div class="card-header">Compatibility with Occupants</div>
                    <div style="font-size:1.5rem;font-weight:800;color:var(--primary)">Aggregate: ${res.result.aggregateScore}%</div>
                    <div style="color:var(--gray);font-size:0.85rem">${res.result.matchingOccupants}/${res.result.totalOccupants} occupants passed deal-breakers</div>
                    ${res.result.individualScores.map(s => html`
                        <div class="match-card mt-2">
                            <div class="match-score">${s.score.passedDealBreakers ? s.score.score + '%' : '✗'}</div>
                            <div class="match-info">
                                <div class="match-name">${esc(s.userName)}</div>
                                ${s.score.isPartial ? html`<div class="completeness-warning">Partial match</div>` : ''}
                                ${!s.score.passedDealBreakers ? html`<div class="completeness-warning">Dealbreakers: ${s.score.dealBreakerFailures?.join(', ')}</div>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        } else {
            container.innerHTML = html`
                <div class="card mt-4">
                    <div class="card-header">Compatibility with Lister</div>
                    <div style="font-size:1.5rem;font-weight:800;color:${res.score.passedDealBreakers ? 'var(--primary)' : 'var(--danger)'}">
                        ${res.score.passedDealBreakers ? res.score.score + '%' : 'Dealbreakers prevent match'}
                    </div>
                    ${res.score.isPartial ? html`<div class="completeness-warning mt-2">Partial match &mdash; profile incomplete</div>` : ''}
                </div>
            `;
        }
    } catch (err) { showToast(err.message, 'error'); }
}

async function toggleFavorite(listingId) {
    try {
        await API.post(`/listings/${listingId}/favorite`);
        showToast('Favorite toggled!');
        router.navigate(`/listings/${listingId}`);
    } catch (err) { showToast(err.message, 'error'); }
}

async function deleteListing(id) {
    if (!confirm('Delete this listing permanently?')) return;
    try {
        await API.delete(`/listings/${id}`);
        showToast('Listing deleted.');
        router.navigate('/my-listings');
    } catch (err) { showToast(err.message, 'error'); }
}

function editListing(id) {
    router.navigate(`/listings/${id}/edit`);
}

async function showPendingConnections() {
    const container = document.getElementById('connectionsContainer');
    try {
        const res = await API.get('/connections/pending');
        if (!res.connections || res.connections.length === 0) {
            container.innerHTML = html`<div class="empty-state"><h3>No Pending Requests</h3></div>`;
            return;
        }
        container.innerHTML = res.connections.map(c => html`
            <div class="connection-item">
                <img src="${c.otherUser?.profilePhotoUrl || '/assets/images/default-avatar.png'}" class="avatar avatar-sm">
                <div style="flex:1">
                    <div style="font-weight:600">${esc(c.otherUser?.name || 'Unknown')}</div>
                    <div style="font-size:0.85rem;color:var(--gray)">${c.direction === 'sent' ? 'Sent' : 'Received'} · ${new Date(c.createdAt?.$date || c.createdAt).toLocaleDateString()}</div>
                </div>
                ${c.direction === 'received' ? html`
                    <button class="btn btn-success btn-sm" onclick="respondToConnection('${c._id}', 'accepted')">Accept</button>
                    <button class="btn btn-danger btn-sm" onclick="respondToConnection('${c._id}', 'declined')">Decline</button>
                ` : html`
                    <span class="tag" style="background:var(--light-gray)">Request Sent</span>
                `}
            </div>
        `).join('');
    } catch (err) { container.innerHTML = html`<div class="alert alert-error">${esc(err.message)}</div>`; }
}

async function showAcceptedConnections() {
    const container = document.getElementById('connectionsContainer');
    try {
        const res = await API.get('/connections/accepted');
        if (!res.connections || res.connections.length === 0) {
            container.innerHTML = html`<div class="empty-state"><h3>No Active Connections</h3></div>`;
            return;
        }
        container.innerHTML = res.connections.map(c => html`
            <div class="connection-item">
                <img src="${c.otherUser?.profilePhotoUrl || '/assets/images/default-avatar.png'}" class="avatar avatar-sm">
                <div style="flex:1">
                    <div style="font-weight:600">${esc(c.otherUser?.name || 'Unknown')}</div>
                    <div style="font-size:0.85rem;color:var(--gray)">Connected</div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="openChat('${c._id}')">Message</button>
            </div>
        `).join('');
    } catch (err) { container.innerHTML = html`<div class="alert alert-error">${esc(err.message)}</div>`; }
}

async function respondToConnection(id, status) {
    try {
        await API.patch(`/connections/${id}/respond`, { status });
        showToast(`Connection ${status}!`);
        showPendingConnections();
    } catch (err) { showToast(err.message, 'error'); }
}

async function openChat(connectionId) {
    router.navigate(`/messages/${connectionId}`);
}

page.chat = async (connectionId) => {
    if (!getAuthUser()) { router.navigate('/login'); return; }
    let isLoading = true;

    const renderChat = () => {
        document.getElementById('pageContent').innerHTML = html`
            <div style="max-width:700px;margin:0 auto;display:flex;flex-direction:column;height:calc(100vh - 200px)">
                <div class="flex items-center gap-4 mb-4">
                    <button class="btn btn-secondary btn-sm" onclick="router.navigate('/connections')">← Back</button>
                    <h2>Messages</h2>
                </div>
                <div id="messagesContainer" style="flex:1;overflow-y:auto;padding:16px;background:var(--white);border:1px solid var(--light-gray);border-radius:var(--radius);margin-bottom:12px;display:flex;flex-direction:column">
                    <div class="text-center" style="color:var(--gray);padding:40px">Loading messages...</div>
                </div>
                <form id="messageForm" style="display:flex;gap:8px">
                    <input type="text" class="form-control" id="messageInput" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            </div>
        `;
    };

    const loadMessages = async () => {
        try {
            const res = await API.get(`/connections/${connectionId}/messages`);
            const container = document.getElementById('messagesContainer');
            if (!res.messages || res.messages.length === 0) {
                container.innerHTML = '<div class="text-center" style="color:var(--gray);padding:40px">No messages yet. Send a message to start the conversation!</div>';
                return;
            }
            const userId = getAuthUser().sub;
            container.innerHTML = res.messages.map(m => {
                const sent = m.sender === userId;
                return html`
                    <div class="message-bubble ${sent ? 'message-sent' : 'message-received'}" style="align-self:${sent ? 'flex-end' : 'flex-start'}">
                        ${esc(m.content)}
                        <div style="font-size:0.75rem;opacity:0.7;margin-top:4px">${new Date(m.createdAt?.$date || m.createdAt).toLocaleTimeString()}</div>
                    </div>
                `;
            }).join('');
            container.scrollTop = container.scrollHeight;
        } catch (err) {
            document.getElementById('messagesContainer').innerHTML = html`<div class="alert alert-error">${esc(err.message)}</div>`;
        }
    };

    renderChat();
    await loadMessages();

    document.getElementById('messageForm').onsubmit = async (e) => {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const content = input.value.trim();
        if (!content) return;
        input.value = '';
        setLoading('messageForm', true);
        try {
            await API.post(`/connections/${connectionId}/messages`, { content });
            setLoading('messageForm', false);
            await loadMessages();
        } catch (err) { setLoading('messageForm', false); showToast(err.message, 'error'); }
    };
};

page.admin = async () => {
    const user = getAuthUser();
    if (!user || user.role !== 'admin') { router.navigate('/'); return; }
    document.getElementById('pageContent').innerHTML = html`
        <h2 class="mb-4">Admin Panel</h2>
        <div class="card mb-4">
            <div class="card-header">Manage Users</div>
            <div class="form-group">
                <input type="text" class="form-control" id="adminSearch" placeholder="Search by name or email..." oninput="page._adminSearch()">
            </div>
            <div id="adminUsersList"><div class="text-center" style="padding:20px;color:var(--gray)">Loading...</div></div>
        </div>
    `;
    page._adminSearch = async () => {
        const q = document.getElementById('adminSearch').value;
        try {
            const res = await API.get(`/admin/users?search=${encodeURIComponent(q)}`);
            const container = document.getElementById('adminUsersList');
            if (!res.users || res.users.length === 0) {
                container.innerHTML = '<div class="text-center" style="padding:20px;color:var(--gray)">No users found.</div>';
                return;
            }
            container.innerHTML = res.users.map(u => html`
                <div class="connection-item">
                    <img src="${u.profilePhotoUrl || '/assets/images/default-avatar.png'}" class="avatar avatar-sm">
                    <div style="flex:1">
                        <div style="font-weight:600">${esc(u.name)}</div>
                        <div style="font-size:0.85rem;color:var(--gray)">${esc(u.email)} ${u.isVerified ? '✅ Verified' : '❌ Unverified'}</div>
                    </div>
                    <button class="btn btn-sm ${u.isVerified ? 'btn-secondary' : 'btn-success'}" onclick="page._toggleVerify('${u._id}')">
                        ${u.isVerified ? 'Unverify' : 'Verify'}
                    </button>
                </div>
            `).join('');
        } catch (err) {
            document.getElementById('adminUsersList').innerHTML = html`<div class="alert alert-error">${esc(err.message)}</div>`;
        }
    };
    page._toggleVerify = async (userId) => {
        try {
            await API.post(`/admin/users/${userId}/verify`);
            showToast('Verification toggled!');
            page._adminSearch();
        } catch (err) { showToast(err.message, 'error'); }
    };
    page._adminSearch();
};

router.register('/', () => page.home());
router.register('/login', () => page.login());
router.register('/register', () => page.register());
router.register('/listings', () => page.listings());
router.register('/listings/create', () => page.createListing());
router.register('/matches', () => page.matches());
router.register('/profile', () => page.profile());
router.register('/connections', () => page.connections());
router.register('/my-listings', () => page.myListings());
router.register('/admin', () => page.admin());

router.register(/^\/listings\/([a-f0-9]+)$/, async (id) => {
    await page.listingDetail(id);
});

router.register(/^\/messages\/([a-f0-9]+)$/, async (id) => {
    await page.chat(id);
});

router.register('*', () => router.navigate('/'));

updateNavbar();
router.handleRoute();
