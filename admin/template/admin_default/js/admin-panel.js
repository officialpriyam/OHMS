/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
(function(window, document) {
    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function normalize(value) {
        return String(value || "").toLowerCase();
    }

    function getColorMode() {
        return document.documentElement.getAttribute("data-admin-color-mode") === "dark" ? "dark" : "light";
    }

    function setColorMode(mode) {
        var nextMode = mode === "dark" ? "dark" : "light";
        var toggle = document.querySelector("[data-admin-theme-toggle]");
        var label = document.querySelector("[data-admin-theme-label]");

        document.documentElement.setAttribute("data-admin-color-mode", nextMode);

        try {
            window.localStorage.setItem("ohms-admin-color-mode", nextMode);
        } catch (error) {
            // Ignore storage failures and keep the current mode for this session.
        }

        if (toggle) {
            toggle.setAttribute("aria-pressed", nextMode === "dark" ? "true" : "false");
            toggle.setAttribute("data-admin-theme-mode", nextMode);
            toggle.setAttribute("title", nextMode === "dark" ? "Switch to light mode" : "Switch to dark mode");
        }

        if (label) {
            label.textContent = nextMode === "dark" ? "Light mode" : "Dark mode";
        }
    }

    ready(function() {
        var body = document.body;
        var sidebar = document.querySelector("[data-admin-sidebar]");
        var navToggle = document.querySelector("[data-admin-menu-toggle]");
        var themeToggle = document.querySelector("[data-admin-theme-toggle]");
        var dropdowns = toArray(document.querySelectorAll("[data-admin-dropdown]"));

        function setDropdownState(dropdown, expanded) {
            var toggle = dropdown ? dropdown.querySelector("[data-admin-dropdown-toggle]") : null;
            var menu = dropdown ? dropdown.querySelector("[data-admin-dropdown-menu]") : null;

            if (!dropdown || !menu) {
                return;
            }

            dropdown.classList.toggle("is-open", expanded);

            if (toggle) {
                toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            }

            menu.hidden = !expanded;
            menu.setAttribute("aria-hidden", expanded ? "false" : "true");
            menu.style.display = expanded ? "block" : "none";
            menu.style.visibility = expanded ? "visible" : "hidden";
            menu.style.opacity = expanded ? "1" : "0";
            menu.style.pointerEvents = expanded ? "auto" : "none";
        }

        function patchLegacyAlerts() {
            var $ = window.jQuery;

            if (!$ || !$.alerts || $.alerts.__adminShellPatched) {
                return;
            }

            var originalShow = $.alerts._show;
            var originalHide = $.alerts._hide;

            $.alerts._show = function() {
                body.classList.add("admin-popup-open");
                return originalShow.apply(this, arguments);
            };

            $.alerts._hide = function() {
                body.classList.remove("admin-popup-open");
                return originalHide.apply(this, arguments);
            };

            $.alerts.__adminShellPatched = true;
        }

        if (themeToggle) {
            setColorMode(getColorMode());
            themeToggle.addEventListener("click", function() {
                setColorMode(getColorMode() === "dark" ? "light" : "dark");
            });
        }

        patchLegacyAlerts();

        function closeDropdown(dropdown) {
            if (!dropdown) {
                return;
            }

            setDropdownState(dropdown, false);
        }

        function openDropdown(dropdown) {
            if (!dropdown) {
                return;
            }

            setDropdownState(dropdown, true);
        }

        function closeAllDropdowns(exceptDropdown) {
            dropdowns.forEach(function(dropdown) {
                if (dropdown !== exceptDropdown) {
                    closeDropdown(dropdown);
                }
            });
        }

        dropdowns.forEach(function(dropdown) {
            var toggle = dropdown.querySelector("[data-admin-dropdown-toggle]");
            var menu = dropdown.querySelector("[data-admin-dropdown-menu]");

            if (menu) {
                setDropdownState(dropdown, false);
            }

            if (!toggle) {
                return;
            }

            toggle.addEventListener("click", function(event) {
                var isOpen = dropdown.classList.contains("is-open");

                event.preventDefault();
                event.stopPropagation();
                closeAllDropdowns(dropdown);

                if (isOpen) {
                    closeDropdown(dropdown);
                    return;
                }

                openDropdown(dropdown);
            });
        });

        closeAllDropdowns(null);

        if (navToggle) {
            navToggle.addEventListener("click", function() {
                body.classList.toggle("admin-nav-open");
            });
        }

        document.addEventListener("click", function(event) {
            closeAllDropdowns(null);

            if (window.innerWidth > 1180 || !body.classList.contains("admin-nav-open") || !sidebar || !navToggle) {
                return;
            }

            if (!sidebar.contains(event.target) && !navToggle.contains(event.target)) {
                body.classList.remove("admin-nav-open");
            }
        });

        document.addEventListener("keydown", function(event) {
            if (event.key === "Escape") {
                closeAllDropdowns(null);
                body.classList.remove("admin-nav-open");
            }
        });

        var navGroups = toArray(document.querySelectorAll("#menu > li"));
        navGroups.forEach(function(group) {
            var sub = group.querySelector("ul.sub");
            var trigger = group.querySelector("a.exp");
            var hasActive = !!group.querySelector("a#current, li.active");

            group.setAttribute("data-nav-has-active", hasActive ? "true" : "false");

            if (sub) {
                sub.hidden = !hasActive;
                sub.style.display = hasActive ? "block" : "none";
            }

            if (hasActive) {
                group.classList.add("is-open");
            }

            if (trigger && sub) {
                trigger.addEventListener("click", function(event) {
                    event.preventDefault();

                    group.classList.toggle("is-open");
                    sub.hidden = !group.classList.contains("is-open");
                    sub.style.display = group.classList.contains("is-open") ? "block" : "none";
                });
            }
        });

        var navSearch = document.querySelector("[data-nav-search]");
        if (navSearch) {
            var applyNavFilter = function() {
                var query = normalize(navSearch.value);

                navGroups.forEach(function(group) {
                    var groupLabel = normalize(group.getAttribute("data-nav-label"));
                    var hasActive = group.getAttribute("data-nav-has-active") === "true";
                    var sub = group.querySelector("ul.sub");
                    var subItems = toArray(group.querySelectorAll("ul.sub > li"));
                    var groupMatched = !query || groupLabel.indexOf(query) !== -1;
                    var visibleSubItems = 0;

                    subItems.forEach(function(item) {
                        var label = normalize(item.getAttribute("data-nav-label"));
                        var matched = !query || groupMatched || label.indexOf(query) !== -1;

                        item.setAttribute("data-nav-hidden", matched ? "false" : "true");
                        if (matched) {
                            visibleSubItems += 1;
                        }
                    });

                    var shouldShow = !query || groupMatched || visibleSubItems > 0;
                    group.setAttribute("data-nav-hidden", shouldShow ? "false" : "true");

                    if (!sub) {
                        return;
                    }

                    if (!shouldShow) {
                        group.classList.remove("is-open");
                        sub.hidden = true;
                        sub.style.display = "none";
                        return;
                    }

                    if (query) {
                        group.classList.add("is-open");
                        sub.hidden = false;
                        sub.style.display = "block";
                        return;
                    }

                    group.classList.toggle("is-open", hasActive);
                    sub.hidden = !hasActive;
                    sub.style.display = hasActive ? "block" : "none";
                });
            };

            navSearch.addEventListener("input", applyNavFilter);
            applyNavFilter();
        }

        var settingsSearch = document.querySelector("[data-settings-search]");
        var settingsGrid = document.querySelector("[data-settings-grid]");

        if (settingsGrid) {
            var cards = toArray(settingsGrid.querySelectorAll("[data-settings-card]"));
            var emptyState = document.querySelector("[data-settings-empty]");
            var countLabel = document.querySelector("[data-settings-count]");
            var filterButtons = toArray(document.querySelectorAll("[data-settings-filter]"));
            var activeGroup = "all";

            var renderSettings = function() {
                var query = normalize(settingsSearch ? settingsSearch.value : "");
                var visibleCards = 0;

                cards.forEach(function(card) {
                    var group = normalize(card.getAttribute("data-settings-group"));
                    var haystack = normalize(card.getAttribute("data-settings-search"));
                    var matchesGroup = activeGroup === "all" || group === activeGroup;
                    var matchesQuery = !query || haystack.indexOf(query) !== -1;
                    var visible = matchesGroup && matchesQuery;

                    card.setAttribute("data-settings-hidden", visible ? "false" : "true");
                    if (visible) {
                        visibleCards += 1;
                    }
                });

                if (countLabel) {
                    countLabel.textContent = visibleCards + " areas";
                }

                if (emptyState) {
                    emptyState.classList.toggle("is-visible", visibleCards === 0);
                }
            };

            if (settingsSearch) {
                settingsSearch.addEventListener("input", renderSettings);
            }

            filterButtons.forEach(function(button) {
                button.addEventListener("click", function() {
                    activeGroup = normalize(button.getAttribute("data-settings-filter")) || "all";

                    filterButtons.forEach(function(otherButton) {
                        otherButton.classList.toggle("is-active", otherButton === button);
                    });

                    renderSettings();
                });
            });

            renderSettings();
        }
    });
})(window, document);
