/**
 * Compose Post JavaScript
 * Post creation logic and interactions
 * CivicOne Theme
 *
 * REFACTORED: Uses ComposeForm namespace and window.ComposeConfig for configuration
 */

(function() {
    'use strict';

    // ============================================
    // COMPOSE FORM NAMESPACE
    // All functions called from onclick handlers
    // ============================================
    window.ComposeForm = {
        pollOptionCount: 2,

        // Haptic feedback helper
        haptic: function() {
            if (navigator.vibrate) navigator.vibrate(10);
        },

        // Tab switching
        switchTab: function(type) {
            const pills = document.querySelectorAll('.multidraw-pill');
            for (let i = 0; i < pills.length; i++) {
                pills[i].classList.remove('active');
                pills[i].setAttribute('aria-selected', 'false');
            }
            const activePill = document.querySelector('.multidraw-pill[data-type="' + type + '"]');
            if (activePill) {
                activePill.classList.add('active');
                activePill.setAttribute('aria-selected', 'true');
            }

            const panels = document.querySelectorAll('.multidraw-panel');
            for (let j = 0; j < panels.length; j++) {
                panels[j].classList.remove('active');
            }
            const activePanel = document.getElementById('panel-' + type);
            if (activePanel) {
                activePanel.classList.add('active');
            }

            const headerBtn = document.getElementById('headerSubmitBtn');
            if (headerBtn) {
                if (type === 'post') {
                    headerBtn.classList.add('visible');
                    headerBtn.textContent = 'Post';
                    headerBtn.setAttribute('form', 'form-post');
                } else {
                    headerBtn.classList.remove('visible');
                }
            }
            this.haptic();
        },

        // Update audience display
        updateAudience: function(select, textId) {
            const text = document.getElementById(textId);
            if (!text) return;
            if (select.value) {
                text.innerHTML = '<i class="fa-solid fa-users" style="margin-right: 4px;"></i> ' + select.options[select.selectedIndex].text;
            } else {
                text.innerHTML = '<i class="fa-solid fa-globe" style="margin-right: 4px;"></i> Public Feed';
            }
        },

        // Listing type toggle (Offer/Request)
        selectListingType: function(type) {
            const btns = document.querySelectorAll('.md-type-btn');
            for (let i = 0; i < btns.length; i++) {
                btns[i].classList.remove('active');
            }
            const activeBtn = document.querySelector('.md-type-btn.' + type);
            if (activeBtn) activeBtn.classList.add('active');

            const input = document.getElementById('listing-type-input');
            if (input) input.value = type;

            const typeText = document.getElementById('listing-type-text');
            const submitText = document.getElementById('listing-submit-text');

            if (type === 'offer') {
                if (typeText) typeText.textContent = 'offering';
                if (submitText) submitText.textContent = 'Create Offer';
            } else {
                if (typeText) typeText.textContent = 'requesting';
                if (submitText) submitText.textContent = 'Create Request';
            }

            // Also filter attributes
            this.filterListingAttributes();
            this.haptic();
        },

        // Listing multi-step navigation
        nextListingStep: function(step) {
            const title = document.getElementById('listing-title');
            const desc = document.getElementById('listing-desc');

            // Validate step 1 before moving to step 2
            if (step === 2) {
                if (!title || !title.value.trim()) {
                    if (title) {
                        title.focus();
                        title.style.borderColor = '#ef4444';
                        setTimeout(function() { title.style.borderColor = ''; }, 400);
                    }
                    return;
                }
                if (!desc || !desc.value.trim()) {
                    if (desc) {
                        desc.focus();
                        desc.style.borderColor = '#ef4444';
                        setTimeout(function() { desc.style.borderColor = ''; }, 400);
                    }
                    return;
                }
            }

            const steps = document.querySelectorAll('#panel-listing .md-step');
            for (let i = 0; i < steps.length; i++) {
                steps[i].classList.remove('active');
            }
            const targetStep = document.getElementById('listing-step-' + step);
            if (targetStep) targetStep.classList.add('active');

            const content = document.getElementById('contentArea');
            if (content) content.scrollTop = 0;
            this.haptic();
        },

        prevListingStep: function(step) {
            const steps = document.querySelectorAll('#panel-listing .md-step');
            for (let i = 0; i < steps.length; i++) {
                steps[i].classList.remove('active');
            }
            const targetStep = document.getElementById('listing-step-' + step);
            if (targetStep) targetStep.classList.add('active');
            this.haptic();
        },

        // Image upload functions
        previewImage: function(input, type) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImg = document.getElementById(type + '-preview-img');
                    const placeholder = document.querySelector('#' + type + '-image-upload .md-image-placeholder');
                    const preview = document.getElementById(type + '-image-preview');

                    if (previewImg) previewImg.src = e.target.result;
                    if (placeholder) placeholder.classList.add('hidden');
                    if (preview) preview.classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        },

        removeImage: function(type) {
            const fileInput = document.getElementById(type + '-image-file');
            const placeholder = document.querySelector('#' + type + '-image-upload .md-image-placeholder');
            const preview = document.getElementById(type + '-image-preview');

            if (fileInput) fileInput.value = '';
            if (placeholder) placeholder.classList.remove('hidden');
            if (preview) preview.classList.add('hidden');
            this.haptic();
        },

        // Toggle AI assist visibility
        toggleAiAssist: function(type, enabled) {
            const wrapper = document.getElementById('ai-wrapper-' + type);
            if (wrapper) {
                if (enabled) {
                    wrapper.classList.add('visible');
                } else {
                    wrapper.classList.remove('visible');
                }
            }
            this.haptic();
        },

        // Poll options
        addPollOption: function() {
            this.pollOptionCount++;
            const container = document.getElementById('poll-options');
            if (!container) return;
            const div = document.createElement('div');
            div.className = 'md-poll-option';
            div.innerHTML = '<input type="text" name="options[]" class="md-input" placeholder="Option ' + this.pollOptionCount + '">' +
                '<button type="button" class="md-poll-remove" onclick="this.parentElement.remove(); ComposeForm.haptic();">' +
                '<i class="fa-solid fa-xmark"></i></button>';
            container.appendChild(div);
            div.querySelector('input').focus();
            this.haptic();
        },

        // Clear group selection
        clearGroupSelection: function() {
            const selectedGroupId = document.getElementById('selected-group-id');
            const selectedGroupDisplay = document.getElementById('selected-group-display');
            const groupSubmitBtn = document.getElementById('group-submit-btn');
            if (selectedGroupId) selectedGroupId.value = '';
            if (selectedGroupDisplay) selectedGroupDisplay.classList.remove('visible');
            if (groupSubmitBtn) groupSubmitBtn.disabled = true;
            this.haptic();
        },

        // SDG Toggle
        toggleSDG: function(checkbox, color) {
            const card = checkbox.closest('.holo-sdg-card');
            if (checkbox.checked) {
                card.style.borderColor = color;
                card.style.backgroundColor = color + '18';
                card.style.boxShadow = '0 4px 15px ' + color + '30';
            } else {
                card.style.borderColor = '';
                card.style.backgroundColor = '';
                card.style.boxShadow = '';
            }
            this.haptic();
        },

        // Location functions
        selectLocationResult: function(pickerId, placeName, lat, lng) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');
            const selectedText = document.getElementById(pickerId + '-selected-text');

            if (input) input.value = placeName;
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;

            if (selected && selectedText) {
                selectedText.textContent = placeName;
                selected.classList.add('visible');
            }

            this.closeAllLocationDropdowns();
            this.haptic();
        },

        detectLocation: function(pickerId) {
            const self = this;
            const btn = document.querySelector('[data-picker-id="' + pickerId + '"] .md-location-btn') ||
                      document.querySelector('#' + pickerId + '-input').closest('.md-location-picker').querySelector('.md-location-btn');

            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser');
                return;
            }

            // Show detecting state
            if (btn) {
                btn.classList.add('detecting');
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            }

            this.haptic();

            const mapboxToken = (window.ComposeConfig && window.ComposeConfig.mapboxToken) || '';

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    // Reverse geocode to get place name
                    if (mapboxToken) {
                        fetch('https://api.mapbox.com/geocoding/v5/mapbox.places/' + longitude + ',' + latitude + '.json?' +
                            'access_token=' + mapboxToken + '&types=place,locality,neighborhood&limit=1')
                            .then(function(response) { return response.json(); })
                            .then(function(data) {
                                if (data.features && data.features.length > 0) {
                                    const placeName = data.features[0].place_name;
                                    self.selectLocationResult(pickerId, placeName, latitude, longitude);
                                } else {
                                    self.selectLocationResult(pickerId, latitude.toFixed(4) + ', ' + longitude.toFixed(4), latitude, longitude);
                                }

                                if (btn) {
                                    btn.classList.remove('detecting');
                                    btn.classList.add('success');
                                    btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                                    setTimeout(function() {
                                        btn.classList.remove('success');
                                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                                    }, 2000);
                                }
                            })
                            .catch(function() {
                                self.selectLocationResult(pickerId, latitude.toFixed(4) + ', ' + longitude.toFixed(4), latitude, longitude);
                                if (btn) {
                                    btn.classList.remove('detecting');
                                    btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                                }
                            });
                    } else {
                        self.selectLocationResult(pickerId, latitude.toFixed(4) + ', ' + longitude.toFixed(4), latitude, longitude);
                        if (btn) {
                            btn.classList.remove('detecting');
                            btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                        }
                    }
                },
                function(error) {
                    console.error('Geolocation error:', error);
                    if (btn) {
                        btn.classList.remove('detecting');
                        btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                    }
                    alert('Unable to detect location. Please ensure location access is enabled.');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        },

        selectRemoteLocation: function(pickerId) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');
            const selectedText = document.getElementById(pickerId + '-selected-text');

            if (input) input.value = 'Remote';
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';

            if (selected && selectedText) {
                selectedText.textContent = 'Remote / Online';
                selected.classList.add('visible');
                const icon = selected.querySelector('.md-location-selected-icon');
                if (icon) icon.innerHTML = '<i class="fa-solid fa-globe"></i>';
            }

            this.closeAllLocationDropdowns();
            this.haptic();
        },

        clearLocation: function(pickerId) {
            const input = document.getElementById(pickerId + '-input');
            const latInput = document.getElementById(pickerId + '-lat');
            const lngInput = document.getElementById(pickerId + '-lng');
            const selected = document.getElementById(pickerId + '-selected');

            if (input) {
                input.value = '';
                input.focus();
            }
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (selected) {
                selected.classList.remove('visible');
                const icon = selected.querySelector('.md-location-selected-icon');
                if (icon) icon.innerHTML = '<i class="fa-solid fa-check"></i>';
            }
            this.haptic();
        },

        closeAllLocationDropdowns: function() {
            document.querySelectorAll('.md-location-suggestions').forEach(function(el) {
                el.classList.remove('visible');
            });
        },

        // Filter listing attributes by category and type
        filterListingAttributes: function() {
            const container = document.getElementById('listing-attributes-container');
            if (!container) return;

            const listingCategorySelect = document.querySelector('#form-listing select[name="category_id"]');
            const selectedCat = listingCategorySelect ? listingCategorySelect.value : '';
            const listingTypeInput = document.getElementById('listing-type-input');
            const selectedType = listingTypeInput ? listingTypeInput.value : 'offer';
            const items = container.querySelectorAll('.attribute-item');
            let visibleCount = 0;

            items.forEach(function(item) {
                const itemCat = item.getAttribute('data-category-id');
                const itemType = item.getAttribute('data-target-type');

                const catMatch = itemCat === 'global' || itemCat == selectedCat;
                const typeMatch = itemType === 'any' || itemType === selectedType;

                if (catMatch && typeMatch) {
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                    const checkbox = item.querySelector('input');
                    if (checkbox) checkbox.checked = false;
                }
            });

            if (visibleCount > 0) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        },

        // AI Content Generation
        generateAiContent: function(type) {
            const self = this;
            const aiDescriptionFields = {
                'listing': 'listing-desc',
                'event': 'event-desc',
                'poll': 'poll-desc',
                'goal': 'goal-desc',
                'volunteering': 'vol-desc'
            };

            const descFieldId = aiDescriptionFields[type];
            const descField = document.getElementById(descFieldId);
            const wrapper = document.getElementById('ai-wrapper-' + type);
            const statusEl = document.getElementById('ai-status-' + type);
            const btn = wrapper ? wrapper.querySelector('.md-ai-generate-btn') : null;

            if (!descField) {
                console.error('Description field not found for type:', type);
                return;
            }

            const userPrompt = descField.value.trim();

            // Check if user has typed something as context
            if (!userPrompt || userPrompt.length < 10) {
                if (statusEl) {
                    statusEl.textContent = 'Please write a brief description first (at least 10 characters) so AI knows what to expand on.';
                    statusEl.className = 'md-ai-status error';
                    setTimeout(function() {
                        statusEl.textContent = '';
                        statusEl.className = 'md-ai-status';
                    }, 4000);
                }
                descField.focus();
                descField.classList.add('shake');
                setTimeout(function() { descField.classList.remove('shake'); }, 400);
                self.haptic();
                return;
            }

            // Show loading state
            if (btn) {
                btn.classList.add('loading');
                btn.disabled = true;
            }
            if (statusEl) {
                statusEl.textContent = 'Generating your description...';
                statusEl.className = 'md-ai-status info';
            }

            const basePath = (window.ComposeConfig && window.ComposeConfig.basePath) || '';
            const apiType = (type === 'volunteering' || type === 'poll' || type === 'goal') ? 'listing' : type;

            fetch(basePath + '/api/ai/generate/' + apiType, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    title: '',
                    context: {
                        user_prompt: userPrompt,
                        type: type
                    }
                })
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Generation failed');
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.content) {
                    descField.value = data.content;
                    if (statusEl) {
                        statusEl.textContent = 'Description generated! Feel free to edit it.';
                        statusEl.className = 'md-ai-status success';
                        setTimeout(function() {
                            statusEl.textContent = '';
                            statusEl.className = 'md-ai-status';
                        }, 3000);
                    }
                    if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
                } else {
                    throw new Error(data.error || 'Generation failed');
                }
            })
            .catch(function(error) {
                console.error('AI generation error:', error);
                if (statusEl) {
                    statusEl.textContent = 'Unable to generate content. Please try again or write manually.';
                    statusEl.className = 'md-ai-status error';
                    setTimeout(function() {
                        statusEl.textContent = '';
                        statusEl.className = 'md-ai-status';
                    }, 4000);
                }
                if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
            })
            .finally(function() {
                if (btn) {
                    btn.classList.remove('loading');
                    btn.disabled = false;
                }
            });
        }
    };

    // ============================================
    // DOMCONTENTLOADED INITIALIZATION
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const config = window.ComposeConfig || {};
        const currentType = config.defaultType || 'post';

        // Initialize header submit button visibility
        const headerSubmitBtn = document.getElementById('headerSubmitBtn');
        if (currentType === 'post' && headerSubmitBtn) {
            headerSubmitBtn.classList.add('visible');
            headerSubmitBtn.textContent = 'Post';
            headerSubmitBtn.setAttribute('form', 'form-post');
        }

        // ============================================
        // GROUP SEARCH (Smart Dropdown)
        // ============================================
        const groupSearch = document.getElementById('group-search');
        const groupResults = document.getElementById('group-results');
        const groupItems = document.querySelectorAll('.md-group-item');
        const selectedGroupDisplay = document.getElementById('selected-group-display');
        const selectedGroupId = document.getElementById('selected-group-id');
        const groupSubmitBtn = document.getElementById('group-submit-btn');

        if (groupSearch) {
            groupSearch.addEventListener('focus', function() {
                if (groupResults) groupResults.classList.add('visible');
            });

            groupSearch.addEventListener('blur', function() {
                setTimeout(function() {
                    if (groupResults) groupResults.classList.remove('visible');
                }, 200);
            });

            groupSearch.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                let hasResults = false;

                groupItems.forEach(function(item) {
                    const name = item.dataset.name.toLowerCase();
                    if (name.includes(query)) {
                        item.classList.remove('hidden');
                        hasResults = true;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                if (hasResults && groupResults) {
                    groupResults.classList.add('visible');
                }
            });

            groupItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;

                    if (selectedGroupId) selectedGroupId.value = id;
                    const nameEl = document.getElementById('selected-group-name');
                    const avatarEl = document.getElementById('selected-group-avatar');
                    if (nameEl) nameEl.textContent = name;
                    if (avatarEl) avatarEl.textContent = name.charAt(0).toUpperCase();

                    if (selectedGroupDisplay) selectedGroupDisplay.classList.add('visible');
                    groupSearch.value = '';
                    if (groupResults) groupResults.classList.remove('visible');
                    if (groupSubmitBtn) groupSubmitBtn.disabled = false;

                    ComposeForm.haptic();
                });
            });
        }

        // ============================================
        // LISTING ATTRIBUTES FILTER BINDING
        // ============================================
        const listingCategorySelect = document.querySelector('#form-listing select[name="category_id"]');
        if (listingCategorySelect) {
            listingCategorySelect.addEventListener('change', function() {
                ComposeForm.filterListingAttributes();
            });
        }

        // Initial filter on load
        ComposeForm.filterListingAttributes();

        // ============================================
        // LOCATION PICKER INITIALIZATION
        // ============================================
        const mapboxToken = config.mapboxToken || '';
        let searchDebounceTimer = null;

        if (mapboxToken && typeof mapboxgl !== 'undefined') {
            mapboxgl.accessToken = mapboxToken;
        }

        // Set up location search on inputs
        document.querySelectorAll('.md-location-picker').forEach(function(picker) {
            const pickerId = picker.dataset.pickerId;
            const input = document.getElementById(pickerId + '-input');
            const suggestions = document.getElementById(pickerId + '-suggestions');

            if (!input) return;

            input.addEventListener('focus', function() {
                if (suggestions) suggestions.classList.add('visible');
            });

            input.addEventListener('blur', function() {
                setTimeout(function() {
                    if (suggestions) suggestions.classList.remove('visible');
                }, 200);
            });

            input.addEventListener('input', function(e) {
                const query = e.target.value.trim();
                if (query.length >= 2 && mapboxToken) {
                    clearTimeout(searchDebounceTimer);
                    searchDebounceTimer = setTimeout(function() {
                        searchLocations(pickerId, query, mapboxToken);
                    }, 300);
                }
            });
        });

        function searchLocations(pickerId, query, token) {
            const resultsContainer = document.getElementById(pickerId + '-results');
            if (!resultsContainer) return;

            resultsContainer.innerHTML = '<div class="md-location-suggestion" style="opacity: 0.6;"><div class="md-location-suggestion-icon"><i class="fa-solid fa-spinner fa-spin"></i></div><div class="md-location-suggestion-text"><div class="md-location-suggestion-main">Searching...</div></div></div>';

            fetch('https://api.mapbox.com/geocoding/v5/mapbox.places/' + encodeURIComponent(query) + '.json?access_token=' + token + '&types=place,locality,neighborhood,address,poi&limit=5&language=en')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.features && data.features.length > 0) {
                        resultsContainer.innerHTML = data.features.map(function(feature) {
                            return '<div class="md-location-suggestion" onclick="ComposeForm.selectLocationResult(\'' + pickerId + '\', \'' + escapeHtml(feature.place_name).replace(/'/g, "\\'") + '\', ' + feature.center[1] + ', ' + feature.center[0] + ')"><div class="md-location-suggestion-icon"><i class="fa-solid fa-location-dot"></i></div><div class="md-location-suggestion-text"><div class="md-location-suggestion-main">' + escapeHtml(feature.text) + '</div><div class="md-location-suggestion-sub">' + escapeHtml(feature.place_name) + '</div></div></div>';
                        }).join('');
                    } else {
                        resultsContainer.innerHTML = '<div class="md-location-suggestion" style="opacity: 0.6;"><div class="md-location-suggestion-icon"><i class="fa-solid fa-circle-question"></i></div><div class="md-location-suggestion-text"><div class="md-location-suggestion-main">No results found</div></div></div>';
                    }
                })
                .catch(function() {
                    resultsContainer.innerHTML = '<div class="md-location-suggestion" style="opacity: 0.6;"><div class="md-location-suggestion-icon"><i class="fa-solid fa-exclamation-triangle"></i></div><div class="md-location-suggestion-text"><div class="md-location-suggestion-main">Search unavailable</div></div></div>';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ============================================
        // PREVENT BODY SCROLL ON MOBILE
        // ============================================
        document.body.style.overflow = 'hidden';
    });

})();
