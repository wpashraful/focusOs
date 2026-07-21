<template>
    <AppLayout title="Lead Intelligence">
        <template #header-actions>
            <div class="header-actions">
                <button class="btn btn--secondary" @click="openIntegrationsModal">
                    ⚙️ Integrations & Keys
                </button>
                <a :href="route('leads.export')" class="btn btn--secondary">
                    📤 Export CSV
                </a>
                <button class="btn btn--primary" @click="enrichAll" :disabled="leads.data.length === 0">
                    ⚡ Enrich All New
                </button>
            </div>
        </template>

        <div class="leads-container">
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card glass-panel border-cyan">
                    <div class="stat-label text-cyan">Total Leads</div>
                    <div class="stat-value">{{ stats.total }}</div>
                </div>
                <div class="stat-card glass-panel border-purple">
                    <div class="stat-label text-purple">Enriched</div>
                    <div class="stat-value">{{ stats.scanned }}</div>
                </div>
                <div class="stat-card glass-panel border-green">
                    <div class="stat-label text-green">Emails Found</div>
                    <div class="stat-value">{{ stats.emails_found }}</div>
                </div>
                <div class="stat-card glass-panel border-indigo">
                    <div class="stat-label text-indigo">Avg Score</div>
                    <div class="stat-value">{{ stats.avg_score }}%</div>
                </div>
                <div class="stat-card glass-panel border-pink">
                    <div class="stat-label text-pink">Outreach Ready</div>
                    <div class="stat-value">{{ stats.pending_emails }}</div>
                </div>
            </div>

            <!-- Main Navigation Tabs -->
            <div class="main-tabs-nav glass-panel">
                <button type="button" class="main-tab-btn" :class="{ 'main-tab-btn--active': currentMainTab === 'leads' }" @click="currentMainTab = 'leads'">
                    📋 Leads List
                </button>
                <button type="button" class="main-tab-btn" :class="{ 'main-tab-btn--active': currentMainTab === 'explorer' }" @click="currentMainTab = 'explorer'">
                    🔍 Keyword Explorer (Matrix)
                </button>
                <button type="button" class="main-tab-btn" :class="{ 'main-tab-btn--active': currentMainTab === 'campaigns' }" @click="currentMainTab = 'campaigns'">
                    🎯 Campaign Targeter
                </button>
                <button type="button" class="main-tab-btn" :class="{ 'main-tab-btn--active': currentMainTab === 'keywords' }" @click="currentMainTab = 'keywords'">
                    📚 Keywords & Locations Library
                </button>
                <button type="button" class="main-tab-btn" :class="{ 'main-tab-btn--active': currentMainTab === 'sessions' }" @click="currentMainTab = 'sessions'">
                    ⏳ Scraping Run Sessions
                </button>
            </div>

            <!-- Leads list tab -->
            <div v-if="currentMainTab === 'leads'" class="main-tab-content">
                <!-- Filter Panel -->
            <div class="filter-panel glass-panel">
                <div class="filter-group">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input
                            type="text"
                            v-model="filters.search"
                            placeholder="Search by name, site, email, location..."
                            @input="applyFilters"
                        />
                    </div>

                    <div class="select-box">
                        <select v-model="filters.status" @change="applyFilters">
                            <option value="">All Statuses</option>
                            <option v-for="st in statuses" :key="st" :value="st">{{ st }}</option>
                        </select>
                    </div>

                    <div class="select-box">
                        <select v-model="filters.score" @change="applyFilters">
                            <option value="">All Lead Scores</option>
                            <option value="high">High Score (>= 75)</option>
                            <option value="medium">Medium Score (50-74)</option>
                            <option value="low">Low Score (< 50)</option>
                        </select>
                    </div>
                </div>

                <div class="workspace-info">
                    <span>Active Workspace: </span>
                    <strong>{{ workspace.name }}</strong>
                </div>
            </div>

            <!-- Table Panel -->
            <div class="table-panel glass-panel">
                <table class="leads-table">
                    <thead>
                        <tr>
                            <th style="width: 80px">Score</th>
                            <th>Company Name</th>
                            <th>Rating</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Socials</th>
                            <th>Techstack</th>
                            <th>Status</th>
                            <th style="text-align: right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in leads.data" :key="lead.id" :class="{ 'row--ignored': lead.status === 'Lost' }" @click="openLeadDetail(lead)" class="clickable-row">
                            <td>
                                <div class="score-badge" :class="getScoreClass(lead.lead_score)">
                                    {{ lead.lead_score }}
                                </div>
                            </td>
                            <td>
                                <div class="company-name">{{ lead.name }}</div>
                                <div class="company-subtext">
                                    <span v-if="lead.website">
                                        <a :href="lead.website" target="_blank" class="lead-link" @click.stop>{{ getDisplayDomain(lead.website) }}</a>
                                    </span>
                                    <span v-else class="text-muted">No Website</span>
                                </div>
                            </td>
                            <td>
                                <div class="rating-cell" v-if="lead.rating">
                                    <span class="star-icon">⭐</span>
                                    <span>{{ lead.rating }}</span>
                                    <span class="reviews-count">({{ lead.reviews_count }})</span>
                                </div>
                                <span class="text-muted" v-else>N/A</span>
                            </td>
                            <td>
                                <span v-if="lead.email && lead.email !== 'N/A'">
                                    📧 {{ lead.email }}
                                </span>
                                <span class="text-muted" v-else>N/A</span>
                            </td>
                            <td>
                                <span v-if="lead.phone">
                                    📞 +{{ lead.phone }}
                                </span>
                                <span class="text-muted" v-else>N/A</span>
                            </td>
                            <td>
                                <div class="social-badges-row">
                                    <a v-if="getSocialUrl(lead, 'facebook')" :href="getSocialUrl(lead, 'facebook')" target="_blank" @click.stop class="social-btn fb" title="Facebook">FB</a>
                                    <a v-if="getSocialUrl(lead, 'instagram')" :href="getSocialUrl(lead, 'instagram')" target="_blank" @click.stop class="social-btn ig" title="Instagram">IG</a>
                                    <a v-if="getSocialUrl(lead, 'linkedin')" :href="getSocialUrl(lead, 'linkedin')" target="_blank" @click.stop class="social-btn li" title="LinkedIn">LI</a>
                                    <a v-if="getSocialUrl(lead, 'whatsapp')" :href="getSocialUrl(lead, 'whatsapp')" target="_blank" @click.stop class="social-btn wa" title="WhatsApp">WA</a>
                                    <a v-if="getSocialUrl(lead, 'youtube')" :href="getSocialUrl(lead, 'youtube')" target="_blank" @click.stop class="social-btn yt" title="YouTube">YT</a>
                                    <span v-if="!getSocialUrl(lead, 'facebook') && !getSocialUrl(lead, 'instagram') && !getSocialUrl(lead, 'linkedin') && !getSocialUrl(lead, 'whatsapp') && !getSocialUrl(lead, 'youtube')" class="text-muted">N/A</span>
                                </div>
                            </td>
                            <td>
                                <span class="tech-tag" v-if="getTechTag(lead)">
                                    {{ getTechTag(lead) }}
                                </span>
                                <span class="text-muted" v-else>Not Scanned</span>
                            </td>
                            <td>
                                <span class="status-badge" :class="getStatusClass(lead.status)">
                                    {{ lead.status }}
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="action-btn" @click.stop="openLeadDetail(lead)">
                                        👁️ View
                                    </button>
                                    <button
                                        class="action-btn action-btn--primary"
                                        @click.stop="scanLead(lead)"
                                        :disabled="lead.status === 'Queued'"
                                    >
                                        ⚡ Enrich
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="leads.data.length === 0">
                            <td colspan="9" class="empty-row">
                                <div class="empty-state">
                                    <div class="empty-icon">🗺️</div>
                                    <h3>No leads found in this workspace</h3>
                                    <p>Sync leads from your Google Maps Lead Scraper Chrome extension to populate this view.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination" v-if="leads.links && leads.links.length > 3">
                    <button
                        v-for="link in leads.links"
                        :key="link.label"
                        class="page-link"
                        :class="{ 'page-link--active': link.active, 'page-link--disabled': !link.url }"
                        @click="navigate(link.url)"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

            <!-- Keyword Explorer (Matrix) Tab -->
            <div v-if="currentMainTab === 'explorer'" class="main-tab-content animation-fade-in">
                <div class="explorer-grid">
                    <!-- Left Panel: Keywords Selector -->
                    <div class="glass-panel">
                        <h3>🔑 Step 1: Select Industry & Variations</h3>
                        
                        <div class="form-group mt-15">
                            <label>Industry Category</label>
                            <select v-model="selectedExplorerIndustry" class="form-control" @change="selectedExplorerRootKeyword = null; selectedExplorerVariations = []">
                                <option :value="null" disabled>Select Industry...</option>
                                <option v-for="ind in industries" :key="ind.id" :value="ind.id">
                                    {{ ind.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="selectedExplorerIndustry" class="form-group mt-15">
                            <label>Root Keyword Category</label>
                            <div class="root-keyword-selectors-grid">
                                <button 
                                    v-for="rk in filteredRootKeywords" 
                                    :key="rk.id"
                                    type="button"
                                    class="rk-select-btn"
                                    :class="{ 'rk-select-btn--active': selectedExplorerRootKeyword?.id === rk.id }"
                                    @click="selectedExplorerRootKeyword = rk; selectedExplorerVariations = []"
                                >
                                    {{ rk.keyword }}
                                </button>
                            </div>
                        </div>

                        <div v-if="selectedExplorerRootKeyword" class="variations-selector-box mt-15">
                            <div class="selector-header">
                                <label>Select Variations (Search Queries)</label>
                                <div class="bulk-toggle-buttons">
                                    <button type="button" class="btn-text" @click="toggleAllExplorerVariations(true)">Check All</button>
                                    <button type="button" class="btn-text" @click="toggleAllExplorerVariations(false)">Uncheck All</button>
                                </div>
                            </div>
                            <div class="variations-checkbox-list">
                                <div v-for="v in selectedExplorerRootKeyword.variations" :key="v.id" class="checkbox-item">
                                    <input 
                                        type="checkbox" 
                                        :id="'var-' + v.id" 
                                        :value="v.keyword" 
                                        v-model="selectedExplorerVariations" 
                                    />
                                    <label :for="'var-' + v.id">{{ v.keyword }}</label>
                                </div>
                            </div>

                            <button 
                                type="button" 
                                class="btn btn--secondary w-full btn-ai-suggest mt-15" 
                                @click="aiGenerateMoreVariations" 
                                :disabled="generatingMoreVariations"
                            >
                                {{ generatingMoreVariations ? '🤖 Generating Variations...' : '🤖 AI Generate 20 More Variations' }}
                            </button>
                        </div>
                    </div>

                    <!-- Right Panel: Locations Selector -->
                    <div class="glass-panel">
                        <h3>📍 Step 2: Select Locations (Cities)</h3>

                        <div class="form-group mt-15">
                            <label>State / Region</label>
                            <select v-model="selectedExplorerState" class="form-control" @change="selectedExplorerCities = []">
                                <option value="" disabled>Select State...</option>
                                <option v-for="st in availableStates" :key="st" :value="st">
                                    {{ st }}
                                </option>
                            </select>
                        </div>

                        <div v-if="selectedExplorerState" class="cities-selector-box mt-15">
                            <div class="selector-header">
                                <label>Select Target Cities</label>
                                <div class="bulk-toggle-buttons">
                                    <button type="button" class="btn-text" @click="toggleAllExplorerCities(true)">Check All</button>
                                    <button type="button" class="btn-text" @click="toggleAllExplorerCities(false)">Uncheck All</button>
                                </div>
                            </div>
                            <div class="cities-checkbox-list">
                                <div v-for="city in filteredCities" :key="city.id" class="checkbox-item">
                                    <input 
                                        type="checkbox" 
                                        :id="'city-' + city.id" 
                                        :value="city.id" 
                                        v-model="selectedExplorerCities" 
                                    />
                                    <label :for="'city-' + city.id">
                                        {{ city.city }} <span class="pop-label" v-if="city.population">(Pop: {{ city.population.toLocaleString() }})</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Matrix Coverage Grid Section -->
                <div v-if="selectedExplorerVariations.length > 0 && selectedExplorerCities.length > 0" class="glass-panel mt-20 animation-fade-in">
                    <div class="matrix-header">
                        <div>
                            <h3>📊 Step 3: Search Coverage Matrix</h3>
                            <p class="text-muted text-sm mt-5">Shows scraping statuses for selected variations and locations. Click run to enqueue only unchecked items.</p>
                        </div>
                        <button 
                            type="button" 
                            class="btn btn--primary btn--lg" 
                            @click="runSelectedMatrix" 
                            :disabled="runningMatrix"
                        >
                            {{ runningMatrix ? 'Enqueuing Tasks...' : '⚡ Run Matrix Scrapes' }}
                        </button>
                    </div>

                    <div class="matrix-table-wrapper mt-15">
                        <table class="matrix-table">
                            <thead>
                                <tr>
                                    <th>City / Search Query</th>
                                    <th v-for="vk in selectedExplorerVariations" :key="vk">
                                        {{ vk }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cid in selectedExplorerCities" :key="cid">
                                    <td class="city-name-cell">
                                        {{ props.locations.find(l => l.id === cid)?.city }}, {{ props.locations.find(l => l.id === cid)?.state }}
                                    </td>
                                    <td v-for="vk in selectedExplorerVariations" :key="vk" class="matrix-cell">
                                        <div v-if="getMatrixCell(vk, cid)" class="matrix-badge-container">
                                            <span 
                                                class="matrix-status-badge"
                                                :class="getMatrixCell(vk, cid).status"
                                            >
                                                <span v-if="getMatrixCell(vk, cid).searched">✓ Done ({{ getMatrixCell(vk, cid).lead_count }} leads)</span>
                                                <span v-else-if="getMatrixCell(vk, cid).status === 'running'">⏳ Running</span>
                                                <span v-else-if="getMatrixCell(vk, cid).status === 'pending'">🕒 Queued</span>
                                                <span v-else-if="getMatrixCell(vk, cid).status === 'failed'">❌ Failed</span>
                                                <span v-else>❌ Unchecked</span>
                                            </span>
                                        </div>
                                        <div v-else>
                                            <span class="matrix-status-badge unchecked">❌ Unchecked</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Campaign Targeter Tab -->
            <div v-if="currentMainTab === 'campaigns'" class="main-tab-content animation-fade-in">
                <div class="glass-panel">
                    <h3>🎯 Quick Scrape Campaign Auto-Targeting</h3>
                    <p class="text-muted text-sm mt-5">Select a single Industry Category, Root Keyword, or Target City to auto-generate all search query terms and queue them up to scrape all areas one-by-one.</p>

                    <div class="form-grid-3 mt-20">
                        <div class="form-group">
                            <label>1. Select Targeting Mode</label>
                            <select v-model="campaignForm.target_type" class="form-control" @change="resetCampaignSelections">
                                <option value="industry">🏢 Target by Industry Category</option>
                                <option value="root_keyword">🔑 Target by Root Keyword Category</option>
                                <option value="city">📍 Target by City / Location</option>
                            </select>
                        </div>

                        <div class="form-group" v-if="campaignForm.target_type === 'industry'">
                            <label>2. Choose Industry</label>
                            <select v-model="campaignForm.target_id" class="form-control" @change="generateCampaignPreview">
                                <option :value="null" disabled>Select Industry...</option>
                                <option v-for="ind in industries" :key="ind.id" :value="ind.id">
                                    {{ ind.name }}
                                </option>
                            </select>
                        </div>

                        <div class="form-group" v-if="campaignForm.target_type === 'root_keyword'">
                            <label>2. Choose Root Keyword</label>
                            <select v-model="campaignForm.target_id" class="form-control" @change="generateCampaignPreview">
                                <option :value="null" disabled>Select Root Keyword...</option>
                                <option v-for="kw in keywords" :key="kw.id" :value="kw.id">
                                    {{ kw.keyword }} ({{ kw.variations.length }} variations)
                                </option>
                            </select>
                        </div>

                        <div class="form-group" v-if="campaignForm.target_type === 'city'">
                            <label>2. Choose City</label>
                            <select v-model="campaignForm.target_id" class="form-control" @change="generateCampaignPreview">
                                <option :value="null" disabled>Select Location...</option>
                                <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                    {{ loc.city }}, {{ loc.state }} ({{ loc.country }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Live Preview Box -->
                    <div v-if="campaignPreview.terms.length > 0" class="campaign-preview-container mt-20">
                        <div class="preview-header">
                            <h4>📋 Campaign Search Terms Preview</h4>
                            <span class="preview-count-badge">{{ campaignPreview.totalCount }} terms generated</span>
                        </div>
                        <p class="text-sm text-muted mt-5" v-if="campaignPreview.totalCount > 10">Showing first 10 items. Launching the campaign will enqueue all {{ campaignPreview.totalCount }} search tasks.</p>
                        <div class="preview-list mt-10">
                            <span v-for="term in campaignPreview.terms.slice(0, 10)" :key="term" class="preview-term-tag">
                                🔍 {{ term }}
                            </span>
                        </div>
                    </div>

                    <div class="campaign-actions mt-25" v-if="campaignForm.target_id">
                        <button 
                            type="button" 
                            class="btn btn--primary btn--lg" 
                            @click="launchCampaign" 
                            :disabled="launchingCampaign || campaignPreview.terms.length === 0"
                        >
                            {{ launchingCampaign ? '⚡ Launching Campaign...' : '⚡ Launch Scrape Campaign' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Keywords & Locations Library Tab -->
            <div v-if="currentMainTab === 'keywords'" class="main-tab-content animation-fade-in">
                <div class="keyword-grid">
                    <!-- Left: Add Root Keyword Category -->
                    <div class="glass-panel">
                        <h3>🔑 Add Root Keyword Category</h3>
                        <form @submit.prevent="suggestKeywords" class="keyword-form mt-15">
                            <div class="form-group">
                                <label>Industry Root Word</label>
                                <input type="text" v-model="rootKeywordInput" placeholder="e.g. Dentist, Roofer, Plumber" class="form-control" required />
                                <small class="text-muted">Enter a single core service name. AI will automatically suggest maps search variations.</small>
                            </div>
                            <div class="form-group mt-10">
                                <label>Link to Industry</label>
                                <select v-model="rootKeywordIndustryId" class="form-control" required>
                                    <option value="" disabled>Select Industry...</option>
                                    <option v-for="ind in industries" :key="ind.id" :value="ind.id">
                                        {{ ind.name }}
                                    </option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn--primary w-full mt-10" :disabled="suggestingKeywords">
                                {{ suggestingKeywords ? '🤖 AI Suggesting...' : '🤖 Add & Generate Variations' }}
                            </button>
                        </form>

                        <div class="panel-divider my-25"></div>

                        <h3>🏢 Manually Add Industry Category</h3>
                        <form @submit.prevent="addIndustry" class="mt-15">
                            <div class="form-group">
                                <label>Industry Name</label>
                                <input type="text" v-model="industryForm.name" placeholder="e.g. Healthcare, Legal" class="form-control" required />
                            </div>
                            <button type="submit" class="btn btn--secondary w-full" :disabled="savingIndustry">
                                Add Industry
                            </button>
                        </form>

                        <div class="panel-divider my-25"></div>

                        <h3>📍 Manually Add Location (City)</h3>
                        <form @submit.prevent="addLocation" class="mt-15">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>City Name</label>
                                    <input type="text" v-model="locationForm.city" placeholder="Miami" class="form-control" required />
                                </div>
                                <div class="form-group">
                                    <label>State / Region</label>
                                    <input type="text" v-model="locationForm.state" placeholder="Florida" class="form-control" required />
                                </div>
                            </div>
                            <div class="form-grid-2 mt-10">
                                <div class="form-group">
                                    <label>Population</label>
                                    <input type="number" v-model="locationForm.population" placeholder="450000" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>Country Code</label>
                                    <input type="text" v-model="locationForm.country" placeholder="US" class="form-control" />
                                </div>
                            </div>
                            <button type="submit" class="btn btn--secondary w-full mt-15" :disabled="savingLocation">
                                Add Location
                            </button>
                        </form>

                        <div class="panel-divider my-25"></div>

                        <h3>📥 CSV Bulk Importers</h3>
                        
                        <!-- Keywords CSV Import -->
                        <form @submit.prevent="handleKeywordsCsvUpload" class="importer-form mt-15">
                            <div class="form-group">
                                <label>Import Keywords CSV (Industry, Keyword)</label>
                                <input 
                                    type="file" 
                                    @change="e => keywordCsvFile = e.target.files[0]" 
                                    accept=".csv" 
                                    class="form-control file-input" 
                                    required 
                                />
                            </div>
                            <button type="submit" class="btn btn--secondary w-full" :disabled="importingKeywords || !keywordCsvFile">
                                {{ importingKeywords ? 'Importing Keywords...' : 'Import Keywords CSV' }}
                            </button>
                        </form>

                        <!-- Cities CSV Import -->
                        <form @submit.prevent="handleCitiesCsvUpload" class="importer-form mt-20">
                            <div class="form-group">
                                <label>Import US Cities CSV (Country, State, City, Lat, Lon, Pop)</label>
                                <input 
                                    type="file" 
                                    @change="e => cityCsvFile = e.target.files[0]" 
                                    accept=".csv" 
                                    class="form-control file-input" 
                                    required 
                                />
                            </div>
                            <button type="submit" class="btn btn--secondary w-full" :disabled="importingCities || !cityCsvFile">
                                {{ importingCities ? 'Importing Cities...' : 'Import Cities CSV' }}
                            </button>
                        </form>

                        <div class="panel-divider my-25"></div>

                        <h3>📤 CSV Bulk Exporters</h3>
                        <div class="mt-15">
                            <a :href="route('leads.keywords.export')" class="btn btn--secondary w-full text-center block" style="display: block;">
                                📤 Export Master Keywords CSV
                            </a>
                            <a :href="route('leads.cities.export')" class="btn btn--secondary w-full text-center block mt-10" style="display: block;">
                                📤 Export Active Locations CSV
                            </a>
                        </div>
                    </div>

                    <!-- Right: Keyword List and Variations -->
                    <div class="glass-panel">
                        <h3>📚 Master Keywords Library</h3>
                        <div class="keyword-library-list mt-15" v-if="keywords && keywords.length">
                            <div v-for="kw in keywords" :key="kw.id" class="keyword-card">
                                <div class="keyword-card-header">
                                    <div class="keyword-card-title">
                                        <h4>{{ kw.keyword }}</h4>
                                        <span class="badge active">{{ kw.variations.length }} variations</span>
                                    </div>
                                    <button type="button" class="btn-delete-keyword" @click="deleteKeyword(kw.id)" title="Delete category">✕</button>
                                </div>
                                <div class="variations-badges">
                                    <span 
                                        v-for="v in kw.variations" 
                                        :key="v.id" 
                                        class="variation-tag variation-tag--clickable"
                                        :class="{ 'variation-tag--selected': selectedVariations.includes(v.keyword) }"
                                        @click="toggleVariationSelection(v.keyword)"
                                    >
                                        {{ v.keyword }}
                                        <span class="delete-variation-btn" @click.stop="deleteVariation(v.id)" title="Remove search term">✕</span>
                                    </span>
                                </div>
                                <!-- Add manual variation inline form -->
                                <div class="manual-variation-form">
                                    <input 
                                        type="text" 
                                        placeholder="+ Add search term manually..." 
                                        v-model="manualKeywordInputs[kw.id]"
                                        @keydown.enter.prevent="addManualVariation(kw.id)"
                                        class="form-control form-control--sm"
                                    />
                                    <button 
                                        type="button" 
                                        class="btn btn--secondary btn--sm" 
                                        @click="addManualVariation(kw.id)"
                                    >
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="empty-state">
                            <div class="empty-icon">🔑</div>
                            <h3>No keywords added yet</h3>
                            <p>Add a root keyword to generate search query variations using OpenAI/Gemini AI.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scraping Run Sessions Tab -->
            <div v-if="currentMainTab === 'sessions'" class="main-tab-content">
                <div class="glass-panel table-panel">
                    <table class="leads-table">
                        <thead>
                            <tr>
                                <th>Campaign / Search Query</th>
                                <th>Status</th>
                                <th>Total Found</th>
                                <th>New Imported</th>
                                <th>Duplicates Filtered</th>
                                <th>Started At</th>
                                <th>Duration / Finished</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="session in sessions" :key="session.id">
                                <td>
                                    <span v-if="session.coverage && session.coverage.variation && session.coverage.location">
                                        {{ session.coverage.variation.keyword }} ({{ session.coverage.location.city }}, {{ session.coverage.location.state }})
                                    </span>
                                    <span v-else class="text-muted">
                                        Ad-hoc Scrape Sync
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge" :class="'status--' + session.status.toLowerCase()">
                                        {{ session.status }}
                                    </span>
                                </td>
                                <td>{{ session.total_found }}</td>
                                <td>
                                    <span class="text-green" style="font-weight: 700">+{{ session.imported }}</span>
                                </td>
                                <td>{{ session.duplicates }}</td>
                                <td>{{ formatDate(session.started_at) }}</td>
                                <td>
                                    <span v-if="session.finished_at">
                                        {{ formatDate(session.finished_at) }}
                                    </span>
                                    <span v-else class="text-pulse">
                                        Running...
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!sessions || sessions.length === 0">
                                <td colspan="7" class="empty-row">
                                    <div class="empty-state">
                                        <div class="empty-icon">⏳</div>
                                        <h3>No scraping sessions found</h3>
                                        <p>Run a campaign search or sync map data from the Chrome scraper extension to log runs.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detail Drawer -->
        <Transition name="drawer">
            <div class="drawer-overlay" v-if="selectedLead" @click="closeLeadDetail">
                <div class="drawer" @click.stop>
                    <div class="drawer-header">
                        <div class="drawer-title">
                            <h2>{{ selectedLead.name }}</h2>
                            <p v-if="selectedLead.address">📍 {{ selectedLead.address }}</p>
                        </div>
                        <button class="close-btn" @click="closeLeadDetail">✕</button>
                    </div>

                    <!-- Drawer Tabs -->
                    <div class="drawer-tabs">
                        <button class="tab-btn" :class="{ 'tab-btn--active': activeTab === 'info' }" @click="activeTab = 'info'">
                            📊 Profile
                        </button>
                        <button class="tab-btn" :class="{ 'tab-btn--active': activeTab === 'audit' }" @click="activeTab = 'audit'">
                            🔍 AI Marketing Audit
                        </button>
                        <button class="tab-btn" :class="{ 'tab-btn--active': activeTab === 'outreach' }" @click="activeTab = 'outreach'">
                            ✉️ Outreach Mail
                        </button>
                    </div>

                    <div class="drawer-content">
                        <!-- TAB 1: Profile & Socials -->
                        <div v-if="activeTab === 'info'" class="tab-content">
                            <div class="detail-section">
                                <h3>Company Profile</h3>
                                <div class="detail-grid">
                                    <div class="grid-item">
                                        <span class="grid-label">Website:</span>
                                        <a v-if="selectedLead.website" :href="selectedLead.website" target="_blank" class="grid-value text-link">
                                            {{ selectedLead.website }} ↗
                                        </a>
                                        <span v-else class="grid-value text-muted">No website</span>
                                    </div>
                                    <div class="grid-item" v-if="selectedLead.phone">
                                        <span class="grid-label">Phone:</span>
                                        <span class="grid-value">+{{ selectedLead.phone }}</span>
                                    </div>
                                    <div class="grid-item" v-if="selectedLead.rating">
                                        <span class="grid-label">Google Rating:</span>
                                        <span class="grid-value">⭐ {{ selectedLead.rating }} ({{ selectedLead.reviews_count }} reviews)</span>
                                    </div>
                                    <div class="grid-item">
                                        <span class="grid-label">Source:</span>
                                        <span class="grid-value">{{ selectedLead.source }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h3>Social Presence</h3>
                                <div class="socials-list">
                                    <a v-for="sc in getSocialLinks(selectedLead)" :key="sc.platform" :href="sc.url" target="_blank" class="social-badge">
                                        <span class="social-icon">{{ getSocialIcon(sc.platform) }}</span>
                                        <span class="social-name">{{ getSocialName(sc.platform) }}</span>
                                    </a>
                                    <span v-if="getSocialLinks(selectedLead).length === 0" class="text-muted">No social links crawled yet.</span>
                                </div>
                            </div>

                            <div class="detail-section" v-if="selectedLead.activities && selectedLead.activities.length">
                                <h3>Lead Activity Log</h3>
                                <div class="activity-log">
                                    <div v-for="act in selectedLead.activities" :key="act.id" class="activity-item">
                                        <div class="activity-bullet" />
                                        <div class="activity-meta">
                                            <span class="activity-type">{{ act.activity_type }}</span>
                                            <span class="activity-time">{{ formatDate(act.created_at) }}</span>
                                        </div>
                                        <p class="activity-desc">{{ act.description }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: AI Audit -->
                        <div v-if="activeTab === 'audit'" class="tab-content">
                            <div v-if="selectedLead.audits && selectedLead.audits.length" class="audit-wrapper">
                                <div class="audit-card strength-card">
                                    <h4>🟢 Strengths</h4>
                                    <ul>
                                        <li v-for="item in selectedLead.audits[0].strengths" :key="item">{{ item }}</li>
                                    </ul>
                                </div>

                                <div class="audit-card gap-card">
                                    <h4>🔴 Gaps / Vulnerabilities</h4>
                                    <ul>
                                        <li v-for="item in selectedLead.audits[0].gaps" :key="item">{{ item }}</li>
                                    </ul>
                                </div>

                                <div class="audit-card suggestion-card">
                                    <h4>💡 Growth Suggestions</h4>
                                    <ul>
                                        <li v-for="item in selectedLead.audits[0].suggestions" :key="item">{{ item }}</li>
                                    </ul>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <div class="empty-icon">🔍</div>
                                <h3>Lead not audited yet</h3>
                                <p>Click the "Enrich" button to scrape the website and generate an AI marketing audit.</p>
                            </div>
                        </div>

                        <!-- TAB 3: Outreach Mailer -->
                        <div v-if="activeTab === 'outreach'" class="tab-content">
                            <div v-if="selectedLead.emails && selectedLead.emails.length" class="email-wrapper">
                                <div class="form-group">
                                    <label>Subject Line</label>
                                    <input type="text" v-model="selectedLead.emails[0].subject" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>Email Body Draft</label>
                                    <textarea v-model="selectedLead.emails[0].body" rows="12" class="form-control text-area"></textarea>
                                </div>

                                <div class="email-meta-row">
                                    <span class="email-status-text">
                                        Mail Status: <strong :class="selectedLead.emails[0].status">{{ selectedLead.emails[0].status }}</strong>
                                    </span>
                                    <span v-if="selectedLead.emails[0].sent_at" class="email-status-text">
                                        Sent: {{ formatDate(selectedLead.emails[0].sent_at) }}
                                    </span>
                                </div>

                                <div class="outreach-actions">
                                    <button class="btn btn--primary w-full" @click="sendOutreachMail(selectedLead)" :disabled="!selectedLead.email || selectedLead.email === 'N/A'">
                                        ✉️ Send Outreach Email
                                    </button>
                                </div>
                            </div>
                            <div v-else class="empty-state">
                                <div class="empty-icon">✉️</div>
                                <h3>No email drafts prepared</h3>
                                <p>Drafts are automatically generated during AI auditing. Run enrichment first.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Integrations Settings Modal -->
        <Transition name="modal">
            <div class="modal-overlay" v-if="integrationsModalOpen" @click="closeIntegrationsModal">
                <div class="modal" @click.stop>
                    <div class="modal-header">
                        <h3>🔑 Lead Intelligence Integrations</h3>
                        <button class="close-btn" @click="closeIntegrationsModal">✕</button>
                    </div>
                    <form @submit.prevent="saveSettings" class="modal-body">
                        <!-- Chrome Extension Webhook -->
                        <div class="form-section">
                            <h4>🗺️ Chrome Extension Setup</h4>
                            <div class="form-group">
                                <label>Your API Integration Webhook Link</label>
                                <div class="copy-link-group">
                                    <input type="text" readonly :value="getWebhookUrl()" class="form-control input-readonly" ref="webhookLink" />
                                    <button type="button" class="btn btn--secondary" @click="copyWebhook">Copy</button>
                                </div>
                                <small class="text-muted">Paste this endpoint into your Google Maps Scraper Chrome extension settings page.</small>
                            </div>
                        </div>

                        <!-- AI Audit API Key -->
                        <div class="form-section">
                            <h4>🤖 AI Auditing & Enrichment</h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>AI Provider</label>
                                    <select v-model="form.ai_provider" class="form-control">
                                        <option value="openai">OpenAI (GPT-4o-mini)</option>
                                        <option value="gemini">Google Gemini (Gemini-1.5-flash)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>API Model Override</label>
                                    <input type="text" v-model="form.ai_model" placeholder="Default model will be used if blank" class="form-control" />
                                </div>
                            </div>
                            <div class="form-group">
                                <label>API Key</label>
                                <input type="password" v-model="form.ai_api_key" placeholder="Enter API Key (Leave blank to keep current)" class="form-control" />
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Crawl Timeout (seconds)</label>
                                    <input type="number" v-model="form.crawl_timeout" min="5" max="120" class="form-control" />
                                </div>
                                <div class="form-group checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" v-model="form.auto_enrich" />
                                        Auto-Enrich leads upon import
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Google Sheets Sync -->
                        <div class="form-section">
                            <h4>📊 Google Sheets Sync Mirror</h4>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Google Sheets Spreadsheet ID</label>
                                    <input type="text" v-model="form.google_sheets_spreadsheet_id" placeholder="Spreadsheet ID key" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>Sheet Worksheet Name</label>
                                    <input type="text" v-model="form.google_sheets_sheet_name" placeholder="Sheet1" class="form-control" />
                                </div>
                            </div>
                            <small class="text-muted">Ensure your FocusOS Google Sheets account is connected via Google OAuth in Settings.</small>
                        </div>

                        <!-- Outreach SMTP Configuration -->
                        <div class="form-section">
                            <h4>✉️ Outreach SMTP Mailer Accounts</h4>
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label>SMTP Host</label>
                                    <input type="text" v-model="form.smtp_host" placeholder="smtp.gmail.com" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>SMTP Port</label>
                                    <input type="number" v-model="form.smtp_port" placeholder="587" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>SMTP Encryption</label>
                                    <select v-model="form.smtp_encryption" class="form-control">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>SMTP Username</label>
                                    <input type="text" v-model="form.smtp_username" placeholder="user@gmail.com" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>SMTP Password</label>
                                    <input type="password" v-model="form.smtp_password" placeholder="SMTP Password" class="form-control" />
                                </div>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Sender From Email</label>
                                    <input type="email" v-model="form.smtp_from_address" placeholder="info@company.com" class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>Sender Display Name</label>
                                    <input type="text" v-model="form.smtp_from_name" placeholder="Outreach Team" class="form-control" />
                                </div>
                            </div>
                            <small class="text-muted">If SMTP details are left blank, outreaches will run in <strong>Sandbox Mode</strong> and write to logs/cold_emails.log.</small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn--secondary" @click="closeIntegrationsModal">Cancel</button>
                            <button type="submit" class="btn btn--primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: Object,
    leads: Object,
    settings: Object,
    workspace: Object,
    projects: Array,
    industries: Array,
    keywords: Array,
    locations: Array,
    coverages: Array,
    sessions: Array,
});

const page = usePage();

const statuses = [
    'Imported',
    'Queued',
    'Website Scanned',
    'Email Found',
    'AI Audited',
    'Ready',
    'Contacted',
    'Interested',
    'Won',
    'Lost'
];

// Active filters
const filters = reactive({
    search: new URLSearchParams(window.location.search).get('search') || '',
    status: new URLSearchParams(window.location.search).get('status') || '',
    score: new URLSearchParams(window.location.search).get('score') || '',
});

const selectedLead = ref(null);
const activeTab = ref('info');
const integrationsModalOpen = ref(false);

const form = reactive({
    ai_provider: props.settings.ai_provider,
    ai_api_key: props.settings.ai_api_key,
    ai_model: props.settings.ai_model,
    crawl_timeout: props.settings.crawl_timeout,
    auto_enrich: props.settings.auto_enrich,
    google_sheets_spreadsheet_id: props.settings.google_sheets_spreadsheet_id,
    google_sheets_sheet_name: props.settings.google_sheets_sheet_name,
    smtp_host: props.settings.smtp_host,
    smtp_port: props.settings.smtp_port,
    smtp_username: props.settings.smtp_username,
    smtp_password: props.settings.smtp_password,
    smtp_encryption: props.settings.smtp_encryption,
    smtp_from_address: props.settings.smtp_from_address,
    smtp_from_name: props.settings.smtp_from_name,
});

// Scraper explorer and library states
const currentMainTab = ref('leads');
const rootKeywordInput = ref('');
const rootKeywordIndustryId = ref('');
const suggestingKeywords = ref(false);

// Campaign Targeter states
const campaignForm = reactive({
    target_type: 'industry',
    target_id: null,
});

const campaignPreview = reactive({
    terms: [],
    totalCount: 0
});

const resetCampaignSelections = () => {
    campaignForm.target_id = null;
    campaignPreview.terms = [];
    campaignPreview.totalCount = 0;
};

const generateCampaignPreview = () => {
    if (!campaignForm.target_id) {
        campaignPreview.terms = [];
        campaignPreview.totalCount = 0;
        return;
    }

    const mode = campaignForm.target_type;
    const id = campaignForm.target_id;
    let list = [];

    if (mode === 'industry') {
        const rootKws = props.keywords.filter(k => k.industry_id === id);
        const activeCities = props.locations.filter(l => l.is_active);
        rootKws.forEach(rk => {
            rk.variations.forEach(v => {
                activeCities.forEach(c => {
                    list.push(`${v.keyword} ${c.city}`);
                });
            });
        });
    } else if (mode === 'root_keyword') {
        const rk = props.keywords.find(k => k.id === id);
        const activeCities = props.locations.filter(l => l.is_active);
        if (rk) {
            rk.variations.forEach(v => {
                activeCities.forEach(c => {
                    list.push(`${v.keyword} ${c.city}`);
                });
            });
        }
    } else if (mode === 'city') {
        const city = props.locations.find(l => l.id === id);
        if (city) {
            props.keywords.forEach(rk => {
                rk.variations.forEach(v => {
                    list.push(`${v.keyword} ${city.city}`);
                });
            });
        }
    }

    campaignPreview.terms = list;
    campaignPreview.totalCount = list.length;
};

const launchingCampaign = ref(false);
const launchCampaign = () => {
    if (!campaignForm.target_id) return;
    launchingCampaign.value = true;
    router.post(route('leads.campaigns.run'), {
        target_type: campaignForm.target_type,
        target_id: campaignForm.target_id
    }, {
        onSuccess: () => {
            launchingCampaign.value = false;
            resetCampaignSelections();
            currentMainTab.value = 'sessions'; // Redirect to run sessions to see progress!
        },
        onError: () => {
            launchingCampaign.value = false;
        }
    });
};

const industryForm = reactive({ name: '' });
const savingIndustry = ref(false);
const addIndustry = () => {
    if (!industryForm.name) return;
    savingIndustry.value = true;
    router.post(route('leads.industries.store'), industryForm, {
        onSuccess: () => {
            industryForm.name = '';
            savingIndustry.value = false;
        },
        onError: () => {
            savingIndustry.value = false;
        }
    });
};

const locationForm = reactive({
    city: '',
    state: '',
    population: null,
    country: 'US',
});
const savingLocation = ref(false);
const addLocation = () => {
    if (!locationForm.city || !locationForm.state) return;
    savingLocation.value = true;
    router.post(route('leads.locations.store'), locationForm, {
        onSuccess: () => {
            locationForm.city = '';
            locationForm.state = '';
            locationForm.population = null;
            savingLocation.value = false;
        },
        onError: () => {
            savingLocation.value = false;
        }
    });
};

const selectedVariations = ref([]);
const manualKeywordInputs = reactive({});

// Keyword Explorer selections
const selectedExplorerIndustry = ref(null);
const selectedExplorerRootKeyword = ref(null);
const selectedExplorerVariations = ref([]);
const selectedExplorerState = ref('');
const selectedExplorerCities = ref([]);

// Filter root keywords based on selected industry in explorer
const filteredRootKeywords = computed(() => {
    if (!selectedExplorerIndustry.value) return [];
    return props.keywords.filter(k => k.industry_id === selectedExplorerIndustry.value);
});

// Group locations by state to select target states
const availableStates = computed(() => {
    if (!props.locations) return [];
    return [...new Set(props.locations.map(l => l.state))].sort();
});

// Filter cities based on selected state in explorer
const filteredCities = computed(() => {
    if (!selectedExplorerState.value) return [];
    return props.locations.filter(l => l.state === selectedExplorerState.value);
});

// Select All / Unselect All helpers
const toggleAllExplorerVariations = (checked) => {
    if (!selectedExplorerRootKeyword.value) return;
    const vars = selectedExplorerRootKeyword.value.variations.map(v => v.keyword);
    if (checked) {
        selectedExplorerVariations.value = [...new Set([...selectedExplorerVariations.value, ...vars])];
    } else {
        selectedExplorerVariations.value = selectedExplorerVariations.value.filter(v => !vars.includes(v));
    }
};

const toggleAllExplorerCities = (checked) => {
    const cityIds = filteredCities.value.map(c => c.id);
    if (checked) {
        selectedExplorerCities.value = [...new Set([...selectedExplorerCities.value, ...cityIds])];
    } else {
        selectedExplorerCities.value = selectedExplorerCities.value.filter(cid => !cityIds.includes(cid));
    }
};

// Check matrix cell status helper
const getMatrixCell = (variationKeyword, cityId) => {
    if (!selectedExplorerRootKeyword.value) return null;
    const variation = selectedExplorerRootKeyword.value.variations.find(v => v.keyword === variationKeyword);
    if (!variation) return null;
    return props.coverages.find(c => c.variation_id === variation.id && c.city_id === cityId);
};

// Run Coverage Matrix trigger
const runningMatrix = ref(false);
const runSelectedMatrix = () => {
    if (selectedExplorerVariations.value.length === 0 || selectedExplorerCities.value.length === 0) return;
    runningMatrix.value = true;
    router.post(route('leads.matrix.run'), {
        variations: selectedExplorerVariations.value,
        cities: selectedExplorerCities.value
    }, {
        onSuccess: () => {
            runningMatrix.value = false;
        },
        onError: () => {
            runningMatrix.value = false;
        }
    });
};

// AI Suggest query variation for current explorer keyword (generate 20 new queries)
const generatingMoreVariations = ref(false);
const aiGenerateMoreVariations = () => {
    if (!selectedExplorerRootKeyword.value) return;
    generatingMoreVariations.value = true;
    router.post(route('leads.keywords.suggest'), {
        root_keyword: selectedExplorerRootKeyword.value.keyword
    }, {
        onSuccess: () => {
            generatingMoreVariations.value = false;
        },
        onError: () => {
            generatingMoreVariations.value = false;
        }
    });
};

// CSV files uploads
const keywordCsvFile = ref(null);
const cityCsvFile = ref(null);
const importingKeywords = ref(false);
const importingCities = ref(false);

const handleKeywordsCsvUpload = () => {
    if (!keywordCsvFile.value) return;
    const formData = new FormData();
    formData.append('csv_file', keywordCsvFile.value);

    importingKeywords.value = true;
    router.post(route('leads.keywords.import'), formData, {
        onSuccess: () => {
            importingKeywords.value = false;
            keywordCsvFile.value = null;
        },
        onError: () => {
            importingKeywords.value = false;
        }
    });
};

const handleCitiesCsvUpload = () => {
    if (!cityCsvFile.value) return;
    const formData = new FormData();
    formData.append('csv_file', cityCsvFile.value);

    importingCities.value = true;
    router.post(route('leads.cities.import'), formData, {
        onSuccess: () => {
            importingCities.value = false;
            cityCsvFile.value = null;
        },
        onError: () => {
            importingCities.value = false;
        }
    });
};

// Selection Batch toolbar states (for backward compatibility)
const toggleVariationSelection = (keyword) => {
    const idx = selectedVariations.value.indexOf(keyword);
    if (idx > -1) {
        selectedVariations.value.splice(idx, 1);
    } else {
        selectedVariations.value.push(keyword);
    }
};

const deleteKeyword = (keywordId) => {
    if (confirm('Are you sure you want to delete this root keyword category? All its variations will be deleted.')) {
        router.delete(route('leads.keywords.destroy', keywordId));
    }
};

const deleteVariation = (variationId) => {
    if (confirm('Are you sure you want to delete this search term variation?')) {
        router.delete(route('leads.variations.destroy', variationId));
    }
};

const addManualVariation = (rootKeywordId) => {
    const inputVal = manualKeywordInputs[rootKeywordId];
    if (!inputVal || !inputVal.trim()) return;

    router.post(route('leads.variations.store'), {
        root_keyword_id: rootKeywordId,
        keyword: inputVal.trim()
    }, {
        onSuccess: () => {
            manualKeywordInputs[rootKeywordId] = '';
        }
    });
};

const suggestKeywords = () => {
    if (!rootKeywordInput.value) return;
    suggestingKeywords.value = true;
    router.post(route('leads.keywords.suggest'), {
        root_keyword: rootKeywordInput.value,
        industry_id: rootKeywordIndustryId.value
    }, {
        onSuccess: () => {
            rootKeywordInput.value = '';
            rootKeywordIndustryId.value = '';
            suggestingKeywords.value = false;
        },
        onError: () => {
            suggestingKeywords.value = false;
        }
    });
};

const applyFilters = () => {
    router.get(route('leads.index'), {
        search: filters.search,
        status: filters.status,
        score: filters.score
    }, {
        preserveState: true,
        replace: true
    });
};

const navigate = (url) => {
    if (url) {
        router.visit(url);
    }
};

const getDisplayDomain = (url) => {
    try {
        const hostname = new URL(url).hostname;
        return hostname.replace('www.', '');
    } catch (e) {
        return url;
    }
};

const getScoreClass = (score) => {
    if (score >= 75) return 'score-badge--high';
    if (score >= 50) return 'score-badge--medium';
    return 'score-badge--low';
};

const getStatusClass = (status) => {
    const map = {
        'Imported': 'status--imported',
        'Queued': 'status--queued',
        'Website Scanned': 'status--scanned',
        'Email Found': 'status--email',
        'AI Audited': 'status--audit',
        'Ready': 'status--ready',
        'Contacted': 'status--contacted',
        'Interested': 'status--interested',
        'Won': 'status--won',
        'Lost': 'status--lost',
    };
    return map[status] || '';
};

const getTechTag = (lead) => {
    const tech = lead.socials.find(s => s.platform === 'website_tech');
    return tech ? tech.url : null;
};

const getSocialUrl = (lead, platform) => {
    if (!lead.socials) return null;
    const soc = lead.socials.find(s => s.platform.toLowerCase() === platform.toLowerCase());
    return soc ? soc.url : null;
};

const getSocialLinks = (lead) => {
    return lead.socials.filter(s => !['website_tech', 'website_phone'].includes(s.platform));
};

const getSocialIcon = (platform) => {
    const icons = {
        facebook: '📱',
        instagram: '📸',
        linkedin: '💼',
        whatsapp: '💬',
        youtube: '🎥',
        contact_page_url: '🔗'
    };
    return icons[platform] || '🔗';
};

const getSocialName = (platform) => {
    const names = {
        facebook: 'Facebook',
        instagram: 'Instagram',
        linkedin: 'LinkedIn',
        whatsapp: 'WhatsApp',
        youtube: 'YouTube',
        contact_page_url: 'Contact Page'
    };
    return names[platform] || platform;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Lead Detailed drawer controls
const openLeadDetail = (lead) => {
    selectedLead.value = lead;
    activeTab.value = 'info';
};

const closeLeadDetail = () => {
    selectedLead.value = null;
};

const scanLead = (lead) => {
    router.post(route('leads.scan', lead.id), {}, {
        onSuccess: () => {
            if (selectedLead.value && selectedLead.value.id === lead.id) {
                // Refresh local state inside drawer
                const refreshed = page.props.leads.data.find(l => l.id === lead.id);
                if (refreshed) {
                    selectedLead.value = refreshed;
                }
            }
        }
    });
};

const sendOutreachMail = (lead) => {
    router.post(route('leads.send-email', lead.id), {}, {
        onSuccess: () => {
            // Refresh local state inside drawer
            const refreshed = page.props.leads.data.find(l => l.id === lead.id);
            if (refreshed) {
                selectedLead.value = refreshed;
            }
        }
    });
};

const enrichAll = () => {
    if (confirm("Are you sure you want to queue enrichment for all Imported and Failed leads?")) {
        router.post(route('leads.enrich-all'));
    }
};

// Integrations Manager Modal controls
const openIntegrationsModal = () => {
    integrationsModalOpen.value = true;
};

const closeIntegrationsModal = () => {
    integrationsModalOpen.value = false;
};

const getWebhookUrl = () => {
    return `${window.location.origin}/api/leads?workspace_id=${props.workspace.id}`;
};

const copyWebhook = () => {
    navigator.clipboard.writeText(getWebhookUrl());
    alert('API Webhook link copied to clipboard!');
};

const saveSettings = () => {
    router.post(route('leads.settings'), form, {
        onSuccess: () => {
            closeIntegrationsModal();
        }
    });
};
</script>

<style scoped>
.leads-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Glassmorphism panel base */
.glass-panel {
    background: rgba(18, 14, 33, 0.45);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.25);
    padding: 20px;
    position: relative;
    overflow: hidden;
}


/* Neon Borders */
.border-cyan { border-left: 4px solid #00f0ff; }
.border-purple { border-left: 4px solid #8b5cf6; }
.border-green { border-left: 4px solid #10b981; }
.border-indigo { border-left: 4px solid #6366f1; }
.border-pink { border-left: 4px solid #ec4899; }

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
}

@media (max-width: 1100px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 6px;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1;
}

.text-cyan { color: #00f0ff; }
.text-purple { color: #a78bfa; }
.text-green { color: #34d399; }
.text-indigo { color: #818cf8; }
.text-pink { color: #f472b6; }

/* Buttons */
.header-actions {
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    text-decoration: none;
    line-height: 1;
}

.btn--primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
}

.btn--primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.45);
}

.btn--secondary {
    background: rgba(255, 255, 255, 0.04);
    color: #cbd5e1;
    border-color: rgba(255, 255, 255, 0.08);
}

.btn--secondary:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
}

.w-full { width: 100%; justify-content: center; }

/* Filter Panel */
.filter-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    padding: 14px 20px;
}

.filter-group {
    display: flex;
    gap: 12px;
    flex: 1;
    min-width: 300px;
}

.search-box {
    position: relative;
    flex: 1;
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.8rem;
}

.search-box input {
    width: 100%;
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 8px 12px 8px 34px;
    color: #e2e8f0;
    font-size: 0.82rem;
    outline: none;
    transition: border-color 0.2s;
}

.search-box input:focus {
    border-color: rgba(99, 102, 241, 0.5);
}

.select-box select {
    background: rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 8px 12px;
    color: #e2e8f0;
    font-size: 0.82rem;
    outline: none;
    cursor: pointer;
    min-width: 140px;
}

.select-box select option,
.form-control option {
    background-color: #0f111a;
    color: #cbd5e1;
}

.workspace-info {
    font-size: 0.78rem;
    color: #94a3b8;
}

/* Leads Table */
.table-panel {
    padding: 0;
    overflow-x: auto;
}

.leads-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.83rem;
    text-align: left;
}

.leads-table th {
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    padding: 14px 18px;
    color: #94a3b8;
    font-weight: 600;
}

.clickable-row {
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.leads-table td {
    padding: 12px 18px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    vertical-align: middle;
}

.leads-table tr:hover {
    background: rgba(255, 255, 255, 0.01);
}

.row--ignored {
    opacity: 0.5;
}

/* Score Badge */
.score-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.78rem;
}

.score-badge--high {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.score-badge--medium {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.score-badge--low {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.company-name {
    font-weight: 600;
    color: #cbd5e1;
}

.company-subtext {
    font-size: 0.72rem;
    margin-top: 2px;
}

.lead-link {
    color: #6366f1;
    text-decoration: none;
}

.lead-link:hover {
    text-decoration: underline;
}

.rating-cell {
    display: flex;
    align-items: center;
    gap: 4px;
}

.star-icon {
    color: #fbbf24;
}

.reviews-count {
    color: #64748b;
    font-size: 0.72rem;
}

.contact-cell {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
}

.contact-icon {
    opacity: 0.7;
}

.tech-tag {
    background: rgba(99, 102, 241, 0.1);
    color: #a5b4fc;
    border: 1px solid rgba(99, 102, 241, 0.15);
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.status--imported { background: rgba(148, 163, 184, 0.1); color: #94a3b8; }
.status--queued { background: rgba(234, 179, 8, 0.1); color: #eab308; animation: pulse 2s infinite; }
.status--scanned { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
.status--email { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
.status--audit { background: rgba(168, 85, 247, 0.1); color: #a855f7; }
.status--ready { background: rgba(236, 72, 153, 0.1); color: #ec72b9; }
.status--contacted { background: rgba(6, 182, 212, 0.1); color: #06b6d4; }
.status--interested { background: rgba(16, 185, 129, 0.1); color: #10b981; }
.status--won { background: #064e3b; color: #34d399; }
.status--lost { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.table-actions {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
}

.action-btn {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 4px;
    padding: 4px 8px;
    color: #cbd5e1;
    font-size: 0.72rem;
    cursor: pointer;
    transition: all 0.15s;
}

.action-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.08);
}

.action-btn--primary {
    background: rgba(99, 102, 241, 0.1);
    color: #a5b4fc;
    border-color: rgba(99, 102, 241, 0.2);
}

.action-btn--primary:hover:not(:disabled) {
    background: rgba(99, 102, 241, 0.2);
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.empty-row {
    padding: 50px 0 !important;
}

.empty-state {
    text-align: center;
    max-width: 400px;
    margin: 0 auto;
}

.empty-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
}

.empty-state h3 {
    font-size: 1rem;
    color: #cbd5e1;
    margin-bottom: 6px;
}

.empty-state p {
    font-size: 0.78rem;
    color: #64748b;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.page-link {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 4px;
    padding: 6px 12px;
    color: #94a3b8;
    font-size: 0.78rem;
    cursor: pointer;
    transition: all 0.15s;
}

.page-link:hover:not(.page-link--disabled) {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
}

.page-link--active {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    color: white !important;
    border-color: transparent;
}

.page-link--disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* Slide-over Drawer */
.drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 1000;
}

.drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: 550px;
    background: #0f111a;
    border-left: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    z-index: 1001;
}

@media (max-width: 600px) {
    .drawer { width: 100%; }
}

.drawer-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.drawer-title h2 {
    font-size: 1.15rem;
    color: #f8fafc;
    margin-bottom: 4px;
}

.drawer-title p {
    font-size: 0.75rem;
    color: #64748b;
}

.close-btn {
    background: none;
    border: none;
    color: #64748b;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 4px;
}

.close-btn:hover {
    color: #e2e8f0;
}

/* Drawer Tabs */
.drawer-tabs {
    display: flex;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    background: rgba(255, 255, 255, 0.01);
}

.tab-btn {
    flex: 1;
    background: none;
    border: none;
    padding: 12px 16px;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.tab-btn:hover {
    color: #cbd5e1;
}

.tab-btn--active {
    color: #a5b4fc !important;
    border-bottom-color: #6366f1;
    background: rgba(99, 102, 241, 0.03);
}

.drawer-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}

.tab-content {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.detail-section h3 {
    font-size: 0.82rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.grid-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.02);
    padding-bottom: 8px;
}

.grid-label {
    color: #64748b;
}

.grid-value {
    color: #cbd5e1;
    font-weight: 500;
}

.text-link {
    color: #6366f1;
    text-decoration: none;
}

.text-link:hover {
    text-decoration: underline;
}

.socials-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.social-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 6px;
    padding: 6px 12px;
    color: #cbd5e1;
    font-size: 0.78rem;
    text-decoration: none;
    transition: all 0.2s;
}

.social-badge:hover {
    background: rgba(99, 102, 241, 0.08);
    border-color: rgba(99, 102, 241, 0.2);
}

.social-icon {
    font-size: 0.9rem;
}

.social-badges-row {
    display: flex;
    gap: 6px;
    align-items: center;
}

.social-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    font-size: 0.68rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}

.social-btn.fb { background: rgba(24, 119, 242, 0.15); color: #1877f2; border: 1px solid rgba(24, 119, 242, 0.25); }
.social-btn.fb:hover { background: #1877f2; color: white; }

.social-btn.ig { background: rgba(225, 48, 108, 0.15); color: #e1306c; border: 1px solid rgba(225, 48, 108, 0.25); }
.social-btn.ig:hover { background: #e1306c; color: white; }

.social-btn.li { background: rgba(10, 102, 194, 0.15); color: #0a66c2; border: 1px solid rgba(10, 102, 194, 0.25); }
.social-btn.li:hover { background: #0a66c2; color: white; }

.social-btn.wa { background: rgba(37, 211, 102, 0.15); color: #25d366; border: 1px solid rgba(37, 211, 102, 0.25); }
.social-btn.wa:hover { background: #25d366; color: white; }

.social-btn.yt { background: rgba(255, 0, 0, 0.15); color: #ff0000; border: 1px solid rgba(255, 0, 0, 0.25); }
.social-btn.yt:hover { background: #ff0000; color: white; }

/* Activity Log */
.activity-log {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: relative;
    padding-left: 14px;
}

.activity-log::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 5px;
    bottom: 5px;
    width: 1px;
    background: rgba(255, 255, 255, 0.08);
}

.activity-item {
    position: relative;
}

.activity-bullet {
    position: absolute;
    left: -14px;
    top: 5px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #475569;
    border: 2px solid #0f111a;
}

.activity-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.7rem;
    margin-bottom: 2px;
}

.activity-type {
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
}

.activity-time {
    color: #64748b;
}

.activity-desc {
    font-size: 0.76rem;
    color: #cbd5e1;
}

/* Audit Wrapper */
.audit-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.audit-card {
    border-radius: 8px;
    padding: 16px;
    border: 1px solid transparent;
}

.audit-card h4 {
    font-size: 0.85rem;
    margin-bottom: 10px;
}

.audit-card ul {
    padding-left: 18px;
    font-size: 0.78rem;
    color: #cbd5e1;
}

.audit-card li {
    margin-bottom: 6px;
}

.strength-card { background: rgba(16, 185, 129, 0.03); border-color: rgba(16, 185, 129, 0.1); color: #34d399; }
.gap-card { background: rgba(239, 68, 68, 0.03); border-color: rgba(239, 68, 68, 0.1); color: #f87171; }
.suggestion-card { background: rgba(99, 102, 241, 0.03); border-color: rgba(99, 102, 241, 0.1); color: #a5b4fc; }

/* Email Wrapper */
.email-wrapper {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #94a3b8;
}

.form-control {
    background: rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    padding: 8px 12px;
    color: #cbd5e1;
    font-size: 0.82rem;
    outline: none;
    font-family: inherit;
}

.form-control:focus {
    border-color: rgba(99, 102, 241, 0.5);
}

.text-area {
    line-height: 1.5;
    resize: vertical;
}

.email-meta-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.72rem;
    color: #64748b;
}

.email-meta-row strong.sent { color: #10b981; }
.email-meta-row strong.draft { color: #f59e0b; }
.email-meta-row strong.failed { color: #ef4444; }

/* Transitions */
.drawer-enter-active, .drawer-leave-active { transition: opacity 0.25s ease; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-active .drawer, .drawer-leave-active .drawer { transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
.drawer-enter-from .drawer, .drawer-leave-to .drawer { transform: translateX(100%); }

/* Modal overlay & container */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal {
    background: #0f111a;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    width: 650px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
}

.modal-body {
    overflow-y: auto;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.form-section {
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    padding-bottom: 20px;
}

.form-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.form-section h4 {
    font-size: 0.85rem;
    color: #a5b4fc;
    margin-bottom: 12px;
    border-left: 3px solid #6366f1;
    padding-left: 8px;
}

.form-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 12px;
}

@media (max-width: 600px) {
    .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
}

.copy-link-group {
    display: flex;
    gap: 8px;
}

.input-readonly {
    flex: 1;
    background: rgba(0, 0, 0, 0.6);
    color: #94a3b8;
    cursor: default;
}

.checkbox-group {
    justify-content: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: #cbd5e1;
    cursor: pointer;
    margin-top: 16px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: rgba(255, 255, 255, 0.01);
}

.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(0.95); }
.text-muted { color: #64748b; font-size: 0.72rem; }

/* ── Light Mode Override Styles ───────────────── */
:global(.focusos-shell:not(.dark-mode)) .glass-panel {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
}

:global(.focusos-shell:not(.dark-mode)) .company-name {
    color: #1e293b !important;
}

:global(.focusos-shell:not(.dark-mode)) .leads-table th {
    background: #f8fafc !important;
    border-bottom-color: #e2e8f0 !important;
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .leads-table td {
    border-bottom-color: #f1f5f9 !important;
    color: #334155 !important;
}

:global(.focusos-shell:not(.dark-mode)) .leads-table tr:hover {
    background: #f8fafc !important;
}

:global(.focusos-shell:not(.dark-mode)) .search-box input,
:global(.focusos-shell:not(.dark-mode)) .select-box select {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .search-box input:focus,
:global(.focusos-shell:not(.dark-mode)) .select-box select:focus {
    border-color: #6366f1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .workspace-info {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .btn--secondary {
    background: #f1f5f9 !important;
    color: #334155 !important;
    border-color: #cbd5e1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .btn--secondary:hover {
    background: #e2e8f0 !important;
}

:global(.focusos-shell:not(.dark-mode)) .page-link {
    background: #f1f5f9 !important;
    color: #475569 !important;
    border-color: #cbd5e1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .page-link:hover:not(.page-link--disabled) {
    background: #e2e8f0 !important;
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .drawer {
    background: #ffffff !important;
    border-left-color: #e2e8f0 !important;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.05) !important;
}

:global(.focusos-shell:not(.dark-mode)) .drawer-header {
    border-bottom-color: #f1f5f9 !important;
}

:global(.focusos-shell:not(.dark-mode)) .drawer-title h2 {
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .drawer-title p {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .drawer-tabs {
    border-bottom-color: #e2e8f0 !important;
    background: #f8fafc !important;
}

:global(.focusos-shell:not(.dark-mode)) .tab-btn {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .tab-btn:hover {
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .tab-btn--active {
    color: #6366f1 !important;
    border-bottom-color: #6366f1 !important;
    background: rgba(99, 102, 241, 0.04) !important;
}

:global(.focusos-shell:not(.dark-mode)) .grid-item {
    border-bottom-color: #f1f5f9 !important;
}

:global(.focusos-shell:not(.dark-mode)) .grid-label {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .grid-value {
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .social-badge {
    background: #f8fafc !important;
    border-color: #e2e8f0 !important;
    color: #334155 !important;
}

:global(.focusos-shell:not(.dark-mode)) .social-badge:hover {
    background: rgba(99, 102, 241, 0.05) !important;
    border-color: #6366f1 !important;
    color: #6366f1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .activity-bullet {
    border-color: #ffffff !important;
    background: #cbd5e1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .activity-log::before {
    background: #e2e8f0 !important;
}

:global(.focusos-shell:not(.dark-mode)) .activity-type {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .activity-desc {
    color: #334155 !important;
}

:global(.focusos-shell:not(.dark-mode)) .audit-card ul {
    color: #1e293b !important;
}

:global(.focusos-shell:not(.dark-mode)) .form-control {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .form-control:focus {
    border-color: #6366f1 !important;
}

:global(.focusos-shell:not(.dark-mode)) .form-group label {
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .modal {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1) !important;
}

:global(.focusos-shell:not(.dark-mode)) .modal-header {
    border-bottom-color: #f1f5f9 !important;
}

:global(.focusos-shell:not(.dark-mode)) .modal-header h3 {
    color: #0f172a !important;
}

:global(.focusos-shell:not(.dark-mode)) .form-section {
    border-bottom-color: #f1f5f9 !important;
}

:global(.focusos-shell:not(.dark-mode)) .form-section h4 {
    color: #4f46e5 !important;
}

:global(.focusos-shell:not(.dark-mode)) .input-readonly {
    background: #f1f5f9 !important;
    color: #475569 !important;
}

:global(.focusos-shell:not(.dark-mode)) .checkbox-label {
    color: #334155 !important;
}

:global(.focusos-shell:not(.dark-mode)) .modal-footer {
    border-top-color: #f1f5f9 !important;
    background: #f8fafc !important;
}

:global(.focusos-shell:not(.dark-mode)) .select-box select option,
:global(.focusos-shell:not(.dark-mode)) .form-control option {
    background-color: #ffffff !important;
    color: #0f172a !important;
}

/* Main Dashboard Tabs */
.main-tabs-nav {
    display: flex;
    gap: 12px;
    padding: 10px 20px;
    margin-bottom: 20px;
}

.main-tab-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.main-tab-btn:hover {
    background: rgba(255, 255, 255, 0.03);
    color: #e2e8f0;
}

.main-tab-btn--active {
    background: rgba(99, 102, 241, 0.1) !important;
    color: #6366f1 !important;
    border: 1px solid rgba(99, 102, 241, 0.2) !important;
}

:global(.focusos-shell:not(.dark-mode)) .main-tab-btn:hover {
    background: rgba(0, 0, 0, 0.03);
    color: #0f172a;
}

:global(.focusos-shell:not(.dark-mode)) .main-tab-btn--active {
    background: rgba(99, 102, 241, 0.05) !important;
    color: #6366f1 !important;
    border-color: rgba(99, 102, 241, 0.15) !important;
}

/* Campaign Grid & variations */
.campaign-grid, .keyword-grid {
    display: grid;
    grid-template-columns: 3fr 2fr;
    gap: 20px;
}

.keyword-grid {
    grid-template-columns: 2fr 3fr;
}

@media (max-width: 900px) {
    .campaign-grid, .keyword-grid {
        grid-template-columns: 1fr;
    }
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.campaign-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.campaign-item {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 8px;
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.campaign-item:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.08);
}

.campaign-item--selected {
    background: rgba(99, 102, 241, 0.05) !important;
    border-color: rgba(99, 102, 241, 0.25) !important;
}

.campaign-item-info h4 {
    margin: 0 0 4px 0;
    font-size: 0.92rem;
    color: #e2e8f0;
}

.campaign-subtext {
    font-size: 0.76rem;
    color: #64748b;
}

.campaign-item-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.badge {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 4px;
}

.badge.draft { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }
.badge.active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.badge.completed { background: rgba(99, 102, 241, 0.15); color: #6366f1; }

.query-count {
    font-size: 0.78rem;
    color: #94a3b8;
}

.campaign-form, .keyword-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

/* Keyword tab */
.keyword-library-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.keyword-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.04);
    border-radius: 8px;
    padding: 16px;
}

.keyword-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    padding-bottom: 8px;
}

.keyword-card-header h4 {
    margin: 0;
    font-size: 0.95rem;
    text-transform: capitalize;
    color: #e2e8f0;
}

.variations-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.variation-tag {
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.15);
    color: #a5b4fc;
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 500;
}

/* Pulse animation for running session duration */
.text-pulse {
    color: #34d399;
    animation: opacityPulse 1.5s infinite alternate;
}

@keyframes opacityPulse {
    from { opacity: 0.4; }
    to { opacity: 1; }
}

/* Light mode adjustments */
:global(.focusos-shell:not(.dark-mode)) .campaign-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
:global(.focusos-shell:not(.dark-mode)) .campaign-item:hover {
    background: #f1f5f9;
}
:global(.focusos-shell:not(.dark-mode)) .campaign-item--selected {
    background: rgba(99, 102, 241, 0.03) !important;
}
:global(.focusos-shell:not(.dark-mode)) .campaign-item-info h4,
:global(.focusos-shell:not(.dark-mode)) .keyword-card-header h4 {
    color: #0f172a;
}
:global(.focusos-shell:not(.dark-mode)) .keyword-card {
    background: #f8fafc;
    border-color: #e2e8f0;
}
:global(.focusos-shell:not(.dark-mode)) .keyword-card-header {
    border-bottom-color: #e2e8f0;
}
:global(.focusos-shell:not(.dark-mode)) .variation-tag {
    background: rgba(99, 102, 241, 0.03);
    border-color: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
}

.variation-tag--clickable {
    cursor: pointer;
    user-select: none;
    transition: all 0.2s ease;
}

.variation-tag--clickable:hover {
    background: rgba(99, 102, 241, 0.2);
    border-color: rgba(99, 102, 241, 0.4);
}

.variation-tag--selected {
    background: #6366f1 !important;
    border-color: #4f46e5 !important;
    color: #ffffff !important;
    box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
}

.batch-selection-toolbar {
    margin-top: 20px;
    padding: 15px;
    background: rgba(99, 102, 241, 0.03);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    animation: slideUp 0.3s ease;
}

.batch-toolbar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 0.85rem;
}

.batch-toolbar-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

@keyframes slideUp {
    from { transform: translateY(10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.keyword-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-delete-keyword {
    background: transparent;
    border: none;
    color: #ef4444;
    font-size: 0.9rem;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s ease;
    padding: 2px 6px;
    border-radius: 4px;
}

.btn-delete-keyword:hover {
    opacity: 1;
    background: rgba(239, 68, 68, 0.1);
}

.delete-variation-btn {
    margin-left: 6px;
    font-size: 0.7rem;
    color: #ef4444;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.2s ease;
    padding: 1px 3px;
    border-radius: 3px;
}

.delete-variation-btn:hover {
    opacity: 1;
    background: rgba(239, 68, 68, 0.2);
}

.manual-variation-form {
    margin-top: 15px;
    display: flex;
    gap: 8px;
    align-items: center;
}

.manual-variation-form .form-control--sm {
    padding: 4px 8px;
    font-size: 0.78rem;
    background: rgba(255, 255, 255, 0.01);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 4px;
    width: 200px;
    color: #e2e8f0;
}

:global(.focusos-shell:not(.dark-mode)) .manual-variation-form .form-control--sm {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #0f172a;
}

.explorer-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.root-keyword-selectors-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.rk-select-btn {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #e2e8f0;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rk-select-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    border-color: rgba(99, 102, 241, 0.3);
}

.rk-select-btn--active {
    background: #6366f1 !important;
    border-color: #4f46e5 !important;
    color: #ffffff !important;
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.3);
}

:global(.focusos-shell:not(.dark-mode)) .rk-select-btn {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #0f172a;
}

:global(.focusos-shell:not(.dark-mode)) .rk-select-btn:hover {
    background: #f1f5f9;
}

.variations-selector-box, .cities-selector-box {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 15px;
}

:global(.focusos-shell:not(.dark-mode)) .variations-selector-box,
:global(.focusos-shell:not(.dark-mode)) .cities-selector-box {
    border-color: #e2e8f0;
}

.selector-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.btn-text {
    background: transparent;
    border: none;
    color: #6366f1;
    font-size: 0.8rem;
    cursor: pointer;
    font-weight: 500;
}

.btn-text:hover {
    text-decoration: underline;
}

.variations-checkbox-list, .cities-checkbox-list {
    max-height: 250px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 10px;
    background: rgba(0, 0, 0, 0.15);
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

:global(.focusos-shell:not(.dark-mode)) .variations-checkbox-list,
:global(.focusos-shell:not(.dark-mode)) .cities-checkbox-list {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.checkbox-item label {
    margin: 0;
    font-size: 0.88rem;
    color: #cbd5e1;
    cursor: pointer;
}

:global(.focusos-shell:not(.dark-mode)) .checkbox-item label {
    color: #334155;
}

.pop-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-left: 4px;
}

.panel-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

:global(.focusos-shell:not(.dark-mode)) .panel-divider {
    border-color: #e2e8f0;
}

.my-25 {
    margin-top: 25px;
    margin-bottom: 25px;
}

.matrix-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.matrix-table-wrapper {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

:global(.focusos-shell:not(.dark-mode)) .matrix-table-wrapper {
    border-color: #e2e8f0;
}

.matrix-table {
    width: 100%;
    border-collapse: collapse;
}

.matrix-table th, .matrix-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    font-size: 0.88rem;
}

:global(.focusos-shell:not(.dark-mode)) .matrix-table th,
:global(.focusos-shell:not(.dark-mode)) .matrix-table td {
    border-color: #e2e8f0;
    color: #0f172a;
}

.matrix-table th {
    background: rgba(0, 0, 0, 0.2);
    font-weight: 600;
    color: #94a3b8;
}

:global(.focusos-shell:not(.dark-mode)) .matrix-table th {
    background: #f1f5f9;
    color: #475569;
}

.city-name-cell {
    font-weight: 600;
    color: #e2e8f0;
}

:global(.focusos-shell:not(.dark-mode)) .city-name-cell {
    color: #0f172a;
}

.matrix-cell {
    text-align: center;
}

.matrix-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.matrix-status-badge.completed {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.2);
}

.matrix-status-badge.running {
    background: rgba(168, 85, 247, 0.1);
    color: #a855f7;
    border: 1px solid rgba(168, 85, 247, 0.2);
    animation: pulse 2s infinite ease-in-out;
}

.matrix-status-badge.pending {
    background: rgba(234, 179, 8, 0.1);
    color: #eab308;
    border: 1px solid rgba(234, 179, 8, 0.2);
}

.matrix-status-badge.unchecked {
    background: rgba(255, 255, 255, 0.03);
    color: #64748b;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

:global(.focusos-shell:not(.dark-mode)) .matrix-status-badge.unchecked {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}

.matrix-status-badge.failed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.file-input {
    padding: 6px 12px;
}

.mt-20 {
    margin-top: 20px;
}

.mt-15 {
    margin-top: 15px;
}

.animation-fade-in {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Campaign Targeter Styles */
.campaign-preview-container {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 15px;
}

:global(.focusos-shell:not(.dark-mode)) .campaign-preview-container {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preview-count-badge {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.preview-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    max-height: 150px;
    overflow-y: auto;
}

.preview-term-tag {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 500;
}

:global(.focusos-shell:not(.dark-mode)) .preview-term-tag {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #334155;
}

.mt-25 {
    margin-top: 25px;
}

.campaign-actions {
    display: flex;
    justify-content: flex-end;
}
</style>
