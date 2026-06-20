window.CraftCrawlInitFriends = function (scope = document) {
    const panel = scope.querySelector('[data-friends-panel]');
    const managerPage = scope.querySelector('[data-friends-manager-page]');
    const profilePage = scope.querySelector('[data-profile-friends-page]');
    const root = managerPage || scope;
    const form = root.querySelector('[data-friends-search-form]');
    const input = root.querySelector('#friend-search-input');
    const results = root.querySelector('[data-friends-search-results]');
    const requestsList = root.querySelector('[data-friend-requests-list]');
    const sentRequestsList = root.querySelector('[data-sent-friend-requests]');
    const currentFriendsList = root.querySelector('[data-current-friends-list]');
    const currentFriendsFilter = root.querySelector('[data-current-friends-filter]');
    const recommendationButtons = root.querySelectorAll('[data-recommendation-id]');
    const suggestedFriendButtons = root.querySelectorAll('[data-suggested-friend-action]');
    const profileFriendButtons = root.querySelectorAll('[data-profile-friend-action]');
    const feed = panel?.querySelector('[data-friends-feed]');
    let feedSentinel = feed?.querySelector('[data-feed-sentinel]') || null;
    const status = root.querySelector('[data-friends-status]');
    const count = panel?.querySelector('[data-friends-count]');
    const menuBadges = document.querySelectorAll('[data-friends-menu-badge]');
    const tabBadges = document.querySelectorAll('[data-friends-tab-badge]');
    const menuToggleBadges = document.querySelectorAll('[data-friends-menu-toggle-badge]');
    const csrfToken = panel?.dataset.csrfToken || managerPage?.dataset.csrfToken || profilePage?.dataset.csrfToken || '';
    let currentStatus = {
        pendingInvites: 0,
        pendingRecommendations: 0,
        socialNotifications: 0,
        newFriends: 0,
        newFeedItems: 0,
        friendsBadgeCount: 0,
        feedBadgeCount: 0,
        badgeCount: 0
    };
    let currentFriendsCache = [];
    let friendSearchTimer = null;
    let friendSearchRequestId = 0;
    let nextFeedCursor = { before: null, key: null };
    let hasMore = false;
    let loadingMore = false;
    let feedObserver = null;
    let feedPaginationFailed = false;
    let feedSeenAt = null;
    const feedPageSize = 40;
    const feedPaginationDelayMs = 1300;
    const reactionLabels = {
        cheers: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 11V18C7 19.1046 7.89543 20 9 20H14C15.1046 20 16 19.1046 16 18V18M7 11C8.30797 11 9.42043 10.163 9.83095 8.99532C9.8869 8.99843 9.94327 9 10 9C10.8885 9 11.6868 8.61375 12.2361 8H15.5C16.3284 8 17 7.32843 17 6.5C17 5.67157 16.3284 5 15.5 5H12.8293C12.4175 3.83481 11.3062 3 10 3C8.69203 3 7.57957 3.83704 7.16905 5.00468C7.1131 5.00157 7.05673 5 7 5C5.34315 5 4 6.34315 4 8C4 9.65685 5.34315 11 7 11ZM16 8V10M16 10H18C19.1046 10 20 10.8954 20 12V16C20 17.1046 19.1046 18 18 18H16M16 10V18"/></svg>',
        nice_find: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.0284 1.11813C9.69728 1.2952 9.53443 1.61638 9.49957 1.97965C9.48456 2.15538 9.46201 2.32986 9.43136 2.50363C9.3663 2.87248 9.24303 3.3937 9.01205 3.98313C8.5513 5.15891 7.67023 6.58926 5.96985 7.65195C3.57358 9.14956 2.68473 12.5146 3.06456 15.527C3.45234 18.6026 5.20871 21.7903 8.68375 22.9486C9.03 23.0641 9.41163 22.9817 9.67942 22.7337C10.0071 22.4303 10.0238 22.0282 9.94052 21.6223C9.87941 21.3244 9.74999 20.5785 9.74999 19.6875C9.74999 19.3992 9.76332 19.1034 9.79413 18.8068C10.3282 20.031 11.0522 20.9238 11.7758 21.5623C12.8522 22.5121 13.8694 22.8574 14.1722 22.9466C14.402 23.0143 14.6462 23.0185 14.8712 22.9284C17.5283 21.8656 19.2011 20.4232 20.1356 18.7742C21.068 17.1288 21.1993 15.3939 20.9907 13.8648C20.7833 12.3436 20.2354 10.9849 19.7537 10.0215C19.3894 9.29292 19.0534 8.77091 18.8992 8.54242C18.7101 8.26241 18.4637 8.04626 18.1128 8.00636C17.8332 7.97456 17.5531 8.06207 17.3413 8.24739L15.7763 9.61686C15.9107 7.44482 15.1466 5.61996 14.1982 4.24472C13.5095 3.24609 12.7237 2.47913 12.1151 1.96354C11.8094 1.70448 11.5443 1.50549 11.3525 1.36923C11.2564 1.30103 11.1784 1.24831 11.1224 1.21142C10.7908 0.99291 10.3931 0.923125 10.0284 1.11813ZM7.76396 20.256C7.75511 20.0744 7.74999 19.8842 7.74999 19.6875C7.75 18.6347 7.89677 17.3059 8.47802 16.0708C8.67271 15.6572 8.91614 15.254 9.21914 14.8753C9.47408 14.5566 9.89709 14.4248 10.2879 14.5423C10.6787 14.6598 10.959 15.003 10.9959 15.4094C11.2221 17.8977 12.2225 19.2892 13.099 20.0626C13.5469 20.4579 13.979 20.7056 14.292 20.8525C15.5 20.9999 17.8849 18.6892 18.3955 17.7882C19.0569 16.6211 19.1756 15.356 19.0091 14.1351C18.8146 12.7092 18.2304 11.3897 17.7656 10.5337L14.6585 13.2525C14.3033 13.5634 13.779 13.5835 13.401 13.3008C13.023 13.018 12.8942 12.5095 13.092 12.0809C14.4081 9.22933 13.655 6.97987 12.5518 5.38019C12.1138 4.74521 11.6209 4.21649 11.18 3.80695C11.0999 4.088 10.9997 4.39262 10.8742 4.71284C10.696 5.16755 10.4662 5.65531 10.1704 6.15187C9.50801 7.26379 8.51483 8.41987 7.02982 9.34797C5.57752 10.2556 4.71646 12.6406 5.04885 15.2768C5.29944 17.2643 6.20241 19.1244 7.76396 20.256Z"/></svg>',
        want_to_go: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M15.9894 4.9502L16.52 4.42014L15.9894 4.9502ZM19.0716 8.03562L18.541 8.56568L19.0716 8.03562ZM8.73837 19.429L8.20777 19.9591L8.73837 19.429ZM4.62169 15.3081L5.15229 14.7781L4.62169 15.3081ZM17.5669 14.9943L17.3032 14.2922L17.5669 14.9943ZM15.6498 15.7146L15.9136 16.4167H15.9136L15.6498 15.7146ZM8.3322 8.38177L7.62798 8.12375L8.3322 8.38177ZM9.02665 6.48636L9.73087 6.74438V6.74438L9.02665 6.48636ZM5.84504 10.6735L6.04438 11.3965L5.84504 10.6735ZM7.30167 10.1351L6.86346 9.52646L6.86346 9.52646L7.30167 10.1351ZM7.67582 9.79038L8.24665 10.2768H8.24665L7.67582 9.79038ZM14.251 16.3805L14.742 16.9475L14.742 16.9475L14.251 16.3805ZM13.3806 18.2012L12.6574 18.0022V18.0022L13.3806 18.2012ZM13.9169 16.7466L13.3075 16.3094L13.3075 16.3094L13.9169 16.7466ZM2.71846 12.7552L1.96848 12.76L1.96848 12.76L2.71846 12.7552ZM2.93045 11.9521L2.28053 11.5778H2.28053L2.93045 11.9521ZM11.3052 21.3431L11.3064 20.5931H11.3064L11.3052 21.3431ZM12.0933 21.1347L11.7215 20.4833L11.7215 20.4833L12.0933 21.1347ZM11.6973 2.03606L11.8588 2.76845L11.6973 2.03606ZM1.4694 21.4699C1.17666 21.763 1.1769 22.2379 1.46994 22.5306C1.76298 22.8233 2.23786 22.8231 2.5306 22.5301L1.4694 21.4699ZM7.18383 17.8721C7.47657 17.5791 7.47633 17.1042 7.18329 16.8114C6.89024 16.5187 6.41537 16.5189 6.12263 16.812L7.18383 17.8721ZM15.4588 5.48026L18.541 8.56568L19.6022 7.50556L16.52 4.42014L15.4588 5.48026ZM9.26897 18.8989L5.15229 14.7781L4.09109 15.8382L8.20777 19.9591L9.26897 18.8989ZM17.3032 14.2922L15.386 15.0125L15.9136 16.4167L17.8307 15.6964L17.3032 14.2922ZM9.03642 8.63979L9.73087 6.74438L8.32243 6.22834L7.62798 8.12375L9.03642 8.63979ZM6.04438 11.3965C6.75583 11.2003 7.29719 11.0625 7.73987 10.7438L6.86346 9.52646C6.69053 9.65097 6.46601 9.72428 5.6457 9.95044L6.04438 11.3965ZM7.62798 8.12375C7.33502 8.92332 7.24338 9.14153 7.10499 9.30391L8.24665 10.2768C8.60041 9.86175 8.7823 9.33337 9.03642 8.63979L7.62798 8.12375ZM7.73987 10.7438C7.92696 10.6091 8.09712 10.4523 8.24665 10.2768L7.10499 9.30391C7.0337 9.38757 6.9526 9.46229 6.86346 9.52646L7.73987 10.7438ZM15.386 15.0125C14.697 15.2714 14.1716 15.4571 13.76 15.8135L14.742 16.9475C14.9028 16.8082 15.1192 16.7152 15.9136 16.4167L15.386 15.0125ZM14.1037 18.4001C14.329 17.5813 14.4021 17.3569 14.5263 17.1838L13.3075 16.3094C12.9902 16.7517 12.8529 17.2919 12.6574 18.0022L14.1037 18.4001ZM13.76 15.8135C13.5903 15.9605 13.4384 16.1269 13.3075 16.3094L14.5263 17.1838C14.5887 17.0968 14.6611 17.0175 14.742 16.9475L13.76 15.8135ZM5.15229 14.7781C4.50615 14.1313 4.06799 13.691 3.78366 13.3338C3.49835 12.9753 3.46889 12.8201 3.46845 12.7505L1.96848 12.76C1.97215 13.3422 2.26127 13.8297 2.61002 14.2679C2.95976 14.7073 3.47115 15.2176 4.09109 15.8382L5.15229 14.7781ZM5.6457 9.95044C4.80048 10.1835 4.10396 10.3743 3.58296 10.5835C3.06341 10.792 2.57116 11.0732 2.28053 11.5778L3.58038 12.3264C3.615 12.2663 3.71693 12.146 4.1418 11.9755C4.56523 11.8055 5.16337 11.6394 6.04438 11.3965L5.6457 9.95044ZM3.46845 12.7505C3.46751 12.6016 3.50616 12.4553 3.58038 12.3264L2.28053 11.5778C2.07354 11.9372 1.96586 12.3452 1.96848 12.76L3.46845 12.7505ZM8.20777 19.9591C8.83164 20.5836 9.34464 21.0987 9.78647 21.4506C10.227 21.8015 10.7179 22.0922 11.3041 22.0931L11.3064 20.5931C11.2369 20.593 11.0814 20.5644 10.721 20.2773C10.3618 19.9912 9.91923 19.5499 9.26897 18.8989L8.20777 19.9591ZM12.6574 18.0022C12.4133 18.8897 12.2462 19.4924 12.0751 19.9188C11.9033 20.3467 11.7821 20.4487 11.7215 20.4833L12.465 21.7861C12.974 21.4956 13.2573 21.0004 13.4671 20.4775C13.6776 19.9532 13.8694 19.2516 14.1037 18.4001L12.6574 18.0022ZM11.3041 22.0931C11.7112 22.0937 12.1114 21.9879 12.465 21.7861L11.7215 20.4833C11.595 20.5555 11.4519 20.5933 11.3064 20.5931L11.3041 22.0931ZM18.541 8.56568C19.6045 9.63022 20.3403 10.3695 20.7917 10.9788C21.2353 11.5774 21.2863 11.8959 21.2321 12.1464L22.6982 12.4634C22.8881 11.5854 22.5382 10.8162 21.9969 10.0857C21.4635 9.36592 20.6305 8.53486 19.6022 7.50556L18.541 8.56568ZM17.8307 15.6964C19.1921 15.1849 20.294 14.773 21.0771 14.3384C21.8718 13.8973 22.5083 13.3416 22.6982 12.4634L21.2321 12.1464C21.178 12.3968 21.0001 12.6655 20.3491 13.0268C19.6865 13.3946 18.7112 13.7632 17.3032 14.2922L17.8307 15.6964ZM16.52 4.42014C15.4841 3.3832 14.6481 2.54353 13.9246 2.00638C13.1908 1.46165 12.4175 1.10912 11.5357 1.30367L11.8588 2.76845C12.1086 2.71335 12.4277 2.7633 13.0304 3.21075C13.6433 3.66579 14.3876 4.40801 15.4588 5.48026L16.52 4.42014ZM9.73087 6.74438C10.2525 5.32075 10.6161 4.33403 10.9812 3.66315C11.3402 3.00338 11.609 2.82357 11.8588 2.76845L11.5357 1.30367C10.654 1.49819 10.1005 2.14332 9.66362 2.94618C9.23278 3.73793 8.82688 4.85154 8.32243 6.22834L9.73087 6.74438ZM2.5306 22.5301L7.18383 17.8721L6.12263 16.812L1.4694 21.4699L2.5306 22.5301Z"/></svg>',
        trophy: '<svg class="feed-reaction-icon" viewBox="0 0 24 24" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.5 4C2.67157 4 2 4.67157 2 5.5C2 6.32843 2.67157 7 3.5 7H4V4H3.5ZM6 4V8C6 11.3137 8.68629 14 12 14C15.3137 14 18 11.3137 18 8V4H6ZM20 4V7H20.5C21.3284 7 22 6.32843 22 5.5C22 4.67157 21.3284 4 20.5 4H20ZM19.9381 9H20.5C22.433 9 24 7.433 24 5.5C24 3.567 22.433 2 20.5 2H19H5H3.5C1.567 2 0 3.567 0 5.5C0 7.433 1.567 9 3.5 9H4.06189C4.51314 12.6187 7.38128 15.4869 11 15.9381V17.5194L6.64922 21H6C5.44772 21 5 21.4477 5 22C5 22.5523 5.44772 23 6 23H7H17H18C18.5523 23 19 22.5523 19 22C19 21.4477 18.5523 21 18 21H17.3508L13 17.5194V15.9381C16.6187 15.4869 19.4869 12.6187 19.9381 9ZM12 19.2806L9.85078 21H14.1492L12 19.2806Z"/></svg>'
    };
    const reactionTextLabels = {
        cheers: 'Cheers',
        nice_find: 'Nice',
        want_to_go: 'Want to Go',
        trophy: 'Trophy'
    };
    const reactionPageSize = 10;
    const reactionTypesByItemType = {
        checkin: ['cheers', 'nice_find'],
        first_visit: ['cheers', 'nice_find'],
        level_up: ['cheers', 'nice_find', 'trophy'],
        event_want: ['cheers', 'nice_find'],
        location_want: ['cheers', 'nice_find', 'want_to_go'],
        badge_earned: ['cheers', 'nice_find', 'trophy'],
        quest_sweep: ['cheers', 'nice_find', 'trophy'],
        user_post: ['cheers', 'nice_find'],
        business_post: ['cheers', 'want_to_go']
    };
    const isUserPath = /\/user\/?$|\/user\//.test(window.location.pathname);
    let focusParams = new URLSearchParams(window.location.search);
    let requestedFocusRequestId = focusParams.get('focus_request');
    let requestedFocusFriendId = focusParams.get('focus_friend');
    let requestedFocusItemKey = focusParams.get('focus_item');
    let requestedFocusSection = focusParams.get('focus_section');
    let requestedFocusReactionType = focusParams.get('focus_reaction_type');
    let requestedFocusReactorId = focusParams.get('focus_reactor');
    let requestedFeedFocusSignature = [
        requestedFocusItemKey,
        requestedFocusSection,
        requestedFocusReactionType,
        requestedFocusReactorId
    ].join('|');
    let hasFocusedRequest = false;
    let hasFocusedFriend = false;
    let hasFocusedFeedItem = false;
    const threadReturnStorageKey = 'craftcrawlFeedThreadReturnItemKey';
    const feedThreadOverlayState = window.CraftCrawlFeedThreadOverlayState || {
        overlay: null,
        content: null,
        itemKey: '',
        baseUrl: '',
        closeTimer: 0
    };
    window.CraftCrawlFeedThreadOverlayState = feedThreadOverlayState;
    let feedThreadPendingReturnItemKey = '';
    let feedThreadPendingReturnUntil = 0;

    function installFeedReactionHandler() {
        if (document.documentElement.dataset.feedReactionReady === 'true') {
            return;
        }

        document.documentElement.dataset.feedReactionReady = 'true';

        // Event delegation for reactions — works for feed lists, feed threads, and business post surfaces.
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-feed-reaction]');
            if (!button || button.disabled) {
                return;
            }

            const tokenSource = button.closest('[data-csrf-token]');
            const requestCsrfToken = tokenSource?.dataset.csrfToken || csrfToken;

            button.disabled = true;
            button.classList.add('is-loading');

            postForm(userEndpoint('feed_reaction_toggle.php'), {
                csrf_token: requestCsrfToken,
                item_key: button.dataset.itemKey,
                reaction_type: button.dataset.reactionType
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Reaction could not be saved.', true);
                        return;
                    }

                    if (data.xp_reward && window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data.xp_reward);
                    }

                    const article = button.closest('article');
                    const reactionsDiv = button.closest('.feed-reactions');
                    if (reactionsDiv && data.reactions) {
                        const itemKey = button.dataset.itemKey;
                        const itemType = article?.dataset.feedItemType || itemKey.split(':')[0];
                        const availableReactions = availableReactionTypes({
                            type: itemType,
                            is_self: article?.dataset.feedIsSelf === 'true'
                        });
                        const reactionMap = {};
                        data.reactions.forEach((r) => { reactionMap[r.type] = r; });
                        reactionsDiv.innerHTML = availableReactions.map((type) => {
                            const reaction = reactionMap[type] || { count: 0, reacted: false };
                            return `<button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(itemKey)}" data-reaction-type="${type}" aria-label="${escapeHtml(reactionTextLabels[type] || type)}">${reactionLabels[type]}${reaction.count > 0 ? ` ${reaction.count}` : ''}</button>`;
                        }).join('');
                    }
                    if (article && data.reaction_entries) {
                        const disclosure = article.querySelector('[data-reaction-disclosure]');
                        const panel = article.querySelector('[data-reaction-disclosure-panel]');
                        const isExpanded = panel && !panel.hidden;
                        if (disclosure) {
                            disclosure.outerHTML = renderReactionDisclosure({
                                item_key: button.dataset.itemKey,
                                unread_reaction_count: Number(disclosure.dataset.unreadCount || 0)
                            });
                        }
                        if (panel) {
                            panel.outerHTML = renderReactionPanel({
                                item_key: button.dataset.itemKey,
                                reaction_entries: data.reaction_entries
                            }, isExpanded);
                        }
                    }
                })
                .catch((error) => showStatus(error.message || 'Reaction could not be saved.', true))
                .finally(() => {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    }

    function userEndpoint(file) {
        return isUserPath ? file : `user/${file}`;
    }

    installFeedReactionHandler();

    if (!panel && !managerPage && !profileFriendButtons.length && !suggestedFriendButtons.length && !menuBadges.length && !tabBadges.length && !menuToggleBadges.length) {
        return;
    }

    const readyTarget = managerPage || panel;
    if (readyTarget && readyTarget.dataset.friendsReady === 'true') {
        return;
    }
    if (readyTarget) {
        readyTarget.dataset.friendsReady = 'true';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function capitalize(value) {
        const text = String(value || '');
        return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
    }

    function formatBusinessType(value) {
        const raw = String(value || '').trim();
        const labels = {
            bar: 'Bar',
            brewery: 'Brewery',
            cidery: 'Cidery',
            distillery: 'Distillery',
            distilery: 'Distillery',
            meadery: 'Meadery',
            social_club: 'Social Club',
            winery: 'Winery'
        };

        const key = raw.toLowerCase();

        if (!raw) {
            return 'Business';
        }

        return labels[key] || key.split('_').filter(Boolean).map(capitalize).join(' ');
    }

    function focusNotificationTarget(element) {
        if (!element) {
            return;
        }

        window.requestAnimationFrame(() => {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            element.classList.add('notification-focus-target');
        });
    }

    function updateFocusRequestFromUrl(value = window.location.href) {
        const url = new URL(value, window.location.href);
        const nextParams = new URLSearchParams(url.search);
        const nextFocusItem = nextParams.get('focus_item');
        const nextFeedFocusSignature = [
            nextFocusItem,
            nextParams.get('focus_section'),
            nextParams.get('focus_reaction_type'),
            nextParams.get('focus_reactor')
        ].join('|');

        if (nextFocusItem && nextFeedFocusSignature !== requestedFeedFocusSignature) {
            hasFocusedFeedItem = false;
        }

        focusParams = nextParams;
        requestedFocusRequestId = focusParams.get('focus_request');
        requestedFocusFriendId = focusParams.get('focus_friend');
        requestedFocusItemKey = focusParams.get('focus_item');
        requestedFocusSection = focusParams.get('focus_section');
        requestedFocusReactionType = focusParams.get('focus_reaction_type');
        requestedFocusReactorId = focusParams.get('focus_reactor');
        requestedFeedFocusSignature = nextFeedFocusSignature;
    }

    function focusReactionEntryIfRequested(item) {
        if (!item || requestedFocusSection !== 'reactions') {
            return;
        }

        const selectorParts = ['[data-reaction-list-item]'];
        if (requestedFocusReactionType) {
            selectorParts.push(`[data-reaction-type="${CSS.escape(requestedFocusReactionType)}"]`);
        }
        if (requestedFocusReactorId) {
            selectorParts.push(`[data-reactor-id="${CSS.escape(requestedFocusReactorId)}"]`);
        }

        const reactionEntry = item.querySelector(selectorParts.join(''));
        if (reactionEntry) {
            focusNotificationTarget(reactionEntry);
        }
    }

    function highlightUnreadReactionEntries(panel) {
        panel?.querySelectorAll('[data-reaction-list-item].is-unread').forEach((entry) => {
            entry.classList.remove('notification-focus-target');
            void entry.offsetWidth;
            entry.classList.add('notification-focus-target');
        });
    }

    function focusFeedItemIfRequested() {
        if (!feed || hasFocusedFeedItem || !requestedFocusItemKey) {
            return;
        }

        const item = feed.querySelector(`[data-feed-item-key="${CSS.escape(requestedFocusItemKey)}"]`);
        if (!item) {
            return;
        }

        hasFocusedFeedItem = true;
        focusNotificationTarget(item);

        if (requestedFocusSection === 'reactions') {
            const captionMore = item.querySelector('[data-feed-caption-more]');
            const toggle = captionMore || item.querySelector('[data-reaction-disclosure-toggle]');
            if (toggle && (captionMore || toggle.getAttribute('aria-expanded') !== 'true')) {
                window.setTimeout(() => {
                    toggle.click();
                    window.setTimeout(() => {
                        highlightUnreadReactionEntries(item.querySelector('[data-reaction-disclosure-panel]'));
                        focusReactionEntryIfRequested(item);
                    }, 120);
                }, 350);
            } else {
                window.setTimeout(() => {
                    highlightUnreadReactionEntries(item.querySelector('[data-reaction-disclosure-panel]'));
                    focusReactionEntryIfRequested(item);
                }, 120);
            }
        }
    }

    function showStatus(message, isError) {
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('form-message-error', isError);
        status.classList.toggle('form-message-success', !isError);
        status.hidden = false;
    }

    function hideStatus() {
        if (status) {
            status.hidden = true;
        }
    }

    function postForm(url, values) {
        const formData = new FormData();

        Object.keys(values).forEach((key) => {
            formData.append(key, values[key]);
        });

        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then((response) => response.json().catch(() => null).then((data) => {
            if (!response.ok || !data) {
                throw new Error((data && data.message) || 'Reaction could not be saved.');
            }

            return data;
        }));
    }

    function formatDate(value) {
        const date = new Date(value.replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function renderAvatar(actor, fallbackName) {
        const data = actor || {};
        const frame = String(data.frame || '').replace(/[^a-z0-9_-]/gi, '');
        const frameStyle = String(data.frame_style || 'solid').replace(/[^a-z0-9_-]/gi, '') || 'solid';
        const classes = `user-avatar user-avatar-medium feed-avatar ${frame ? `has-frame-${frame} has-frame-style-${frameStyle}` : ''}`;
        const name = data.name || fallbackName || 'A friend';
        const initials = data.initials || String(name).split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase() || 'CC';
        const userId = Number.parseInt(data.id, 10);
        const profileUrl = userId > 0 ? `profile.php?id=${encodeURIComponent(userId)}` : '';
        const label = name === 'You' ? 'View your profile' : `View ${name}'s profile`;
        let avatar = '';

        if (data.avatar_url) {
            avatar = `<span class="${classes}"><img src="${escapeHtml(data.avatar_url)}" alt="${escapeHtml(name)} profile photo"></span>`;
        } else {
            avatar = `<span class="${classes}" aria-label="${escapeHtml(name)} profile photo"><span>${escapeHtml(initials)}</span></span>`;
        }

        if (!profileUrl) {
            return avatar;
        }

        return `<a class="user-avatar-link feed-avatar-link" href="${profileUrl}" aria-label="${escapeHtml(label)}">${avatar}</a>`;
    }

    function isNewFeedItem(item) {
        if (!feedSeenAt || !item.created_at) return false;
        return new Date(item.created_at) > new Date(feedSeenAt);
    }

    function feedItemAttrs(item) {
        return `data-feed-item-key="${escapeHtml(item.item_key || '')}" data-feed-item-type="${escapeHtml(item.type || '')}" data-feed-is-self="${item.is_self ? 'true' : 'false'}"`;
    }

    function feedItemClasses(item, extra) {
        let cls = 'friends-feed-item';
        if (extra) cls += ' ' + extra;
        if (isNewFeedItem(item)) cls += ' is-new';
        return cls;
    }

    function renderBusinessLink(item) {
        return `<a class="feed-business-link" href="../business_details.php?id=${encodeURIComponent(item.business_id)}">${escapeHtml(item.business_name)}</a>`;
    }

    function renderFeedMeta(label, date, extraHtml = '') {
        return `
            <p class="feed-item-meta">
                <span>${escapeHtml(label)}</span>
                ${date ? `<span aria-hidden="true">·</span><time>${escapeHtml(date)}</time>` : ''}
                ${extraHtml ? `<span aria-hidden="true">·</span>${extraHtml}` : ''}
            </p>
        `;
    }

    function availableReactionTypes(item) {
        const types = reactionTypesByItemType[item.type] || Object.keys(reactionLabels);

        if (item.is_self && item.type !== 'business_post') {
            return types.filter((type) => type !== 'want_to_go');
        }

        return types;
    }

    function applyStatusCounts(data) {
        const counts = data?.counts || data || {};
        const pendingInvites = Number(counts.pending_invites || 0);
        const pendingRecommendations = Number(counts.pending_recommendations || 0);
        const socialNotifications = Number(counts.social_notifications || 0);
        const newFriends = Number(counts.new_friends || 0);
        const newFeedItems = Number(counts.new_feed_items || 0);
        const friendsBadgeCount = pendingInvites + pendingRecommendations + newFriends;
        const feedBadgeCount = newFeedItems + socialNotifications;
        const badgeCount = Number(counts.badge_count || 0);

        currentStatus = {
            pendingInvites,
            pendingRecommendations,
            socialNotifications,
            newFriends,
            newFeedItems,
            friendsBadgeCount,
            feedBadgeCount,
            badgeCount
        };
        menuBadges.forEach((badge) => setBadge(badge, friendsBadgeCount));
        menuToggleBadges.forEach((badge) => setBadge(badge, friendsBadgeCount));
        tabBadges.forEach((badge) => setBadge(badge, feedBadgeCount));
        syncNativeAppBadge(badgeCount);
    }

    function clearLocalSocialNotificationCount(value) {
        const clearedCount = Math.max(0, Number(value || 0));
        if (clearedCount < 1) {
            return;
        }

        applyStatusCounts({
            pending_invites: currentStatus.pendingInvites,
            pending_recommendations: currentStatus.pendingRecommendations,
            social_notifications: Math.max(0, currentStatus.socialNotifications - clearedCount),
            new_friends: currentStatus.newFriends,
            new_feed_items: currentStatus.newFeedItems,
            badge_count: Math.max(0, currentStatus.badgeCount - clearedCount)
        });
    }

    function loadStatus() {
        return fetch(userEndpoint('friend_status.php'), { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    return;
                }

                applyStatusCounts(data);
            })
            .catch(() => {});
    }

    function markFriendsSeen(scope = 'all') {
        if (!csrfToken) {
            return Promise.resolve();
        }

        return postForm(userEndpoint('friend_seen.php'), {
            csrf_token: csrfToken,
            scope
        })
            .then(() => loadStatus())
            .catch(() => {});
    }

    function markReactionNotificationsSeen(disclosure) {
        if (!csrfToken || !disclosure || disclosure.dataset.markingSeen === 'true') {
            return Promise.resolve();
        }

        const visibleBadgeText = disclosure.querySelector('.feed-action-unread-badge')?.textContent || '';
        const visibleBadgeCount = visibleBadgeText.trim() === '9+' ? 9 : Number(visibleBadgeText.trim() || 0);
        const unreadCount = Math.max(Number(disclosure.dataset.unreadCount || 0), visibleBadgeCount);
        disclosure.dataset.markingSeen = 'true';
        disclosure.dataset.unreadCount = '0';
        disclosure.querySelectorAll('.feed-action-unread-badge').forEach((badge) => badge.remove());
        clearLocalSocialNotificationCount(unreadCount);

        return postForm(userEndpoint('feed_notification_seen.php'), {
            csrf_token: csrfToken,
            item_key: disclosure.dataset.itemKey,
            notification_type: 'reaction'
        })
            .then((data) => {
                if (!data.ok) {
                    return;
                }
                applyStatusCounts(data);
            })
            .catch(() => {})
            .finally(() => {
                delete disclosure.dataset.markingSeen;
            });
    }

    function setBadge(element, value) {
        if (!element) {
            return;
        }

        element.textContent = value > 9 ? '9+' : String(value);
        element.hidden = value < 1;
    }

    function getNativeBadgePlugin() {
        const capacitor = window.Capacitor;
        if (!capacitor
            || typeof capacitor.isNativePlatform !== 'function'
            || !capacitor.isNativePlatform()
            || (typeof capacitor.getPlatform === 'function' && capacitor.getPlatform() !== 'ios')) {
            return null;
        }

        return capacitor.Plugins?.CraftCrawlBadge
            || (typeof capacitor.registerPlugin === 'function'
                ? capacitor.registerPlugin('CraftCrawlBadge')
                : null);
    }

    function syncNativeAppBadge(value) {
        const badgePlugin = getNativeBadgePlugin();
        if (!badgePlugin || typeof badgePlugin.setBadgeCount !== 'function') {
            return;
        }

        badgePlugin.setBadgeCount({ count: Math.max(0, Number(value || 0)) }).catch(() => {});
    }

    function renderSearchResults(users) {
        if (!results) {
            return;
        }

        if (!users.length) {
            results.innerHTML = '<p>No matching user accounts found.</p>';
            results.hidden = false;
            return;
        }

        results.innerHTML = users.map((user) => {
            let buttonText = 'Invite';
            let disabled = '';
            let action = 'invite';
            let statusMarkup = '';
            let rowClass = '';

            if (user.is_friend) {
                buttonText = 'Added';
                disabled = 'disabled';
                statusMarkup = '<span class="friend-search-status is-friend">Already friends</span>';
            } else if (user.pending_sent) {
                buttonText = 'Cancel Invite';
                action = 'cancel';
                rowClass = ' is-invite-sent';
                statusMarkup = '<span class="friend-search-status is-sent">✓ Invitation sent</span>';
            } else if (user.pending_received) {
                buttonText = 'Accept Invite';
                action = 'accept';
                statusMarkup = '<span class="friend-search-status is-received">Invited you</span>';
            }

            return `
                <article class="friend-search-result${rowClass}">
                    ${renderAvatar(user.actor, user.name)}
                    <div class="friend-search-summary">
                        <strong>${escapeHtml(user.name)}</strong>
                        <span class="friend-search-meta">Level ${escapeHtml(user.level || 1)}${user.title ? ` &middot; ${escapeHtml(user.title)}` : ''}</span>
                        <div class="friend-card-action-row">
                            <span class="friend-search-username">@${escapeHtml(user.username || '')}</span>
                            <button type="button" data-friend-id="${user.id}" data-request-id="${user.received_request_id || user.sent_request_id || ''}" data-friend-action="${action}" ${disabled}>
                                ${buttonText}
                            </button>
                        </div>
                        ${statusMarkup}
                    </div>
                    <a class="friend-card-link" href="profile.php?id=${encodeURIComponent(user.id)}" aria-label="View ${escapeHtml(user.name)}'s profile"></a>
                </article>
            `;
        }).join('');
        results.hidden = false;

        results.querySelectorAll('button[data-friend-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.friendAction;
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = action === 'accept'
                    ? 'Accepting...'
                    : action === 'cancel'
                        ? 'Canceling...'
                        : 'Sending...';

                const request = action === 'accept'
                    ? postForm(userEndpoint('friend_respond.php'), {
                        csrf_token: csrfToken,
                        request_id: button.dataset.requestId,
                        response: 'accepted'
                    })
                    : action === 'cancel'
                        ? postForm(userEndpoint('friend_cancel.php'), {
                            csrf_token: csrfToken,
                            request_id: button.dataset.requestId
                        })
                    : postForm(userEndpoint('friend_add.php'), {
                        csrf_token: csrfToken,
                        friend_id: button.dataset.friendId
                    });

                request
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be added.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = action === 'accept'
                                ? 'Accept Invite'
                                : action === 'cancel'
                                    ? 'Cancel Invite'
                                    : 'Invite';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        if (data.xp_reward && window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data.xp_reward);
                        }
                        button.classList.remove('is-loading');
                        const row = button.closest('.friend-search-result');
                        const summary = row?.querySelector('.friend-search-summary');

                        if (action === 'cancel') {
                            button.disabled = false;
                            button.textContent = 'Invite';
                            button.dataset.friendAction = 'invite';
                            button.dataset.requestId = '';
                            if (row && summary) {
                                row.classList.remove('is-invite-sent');
                                summary.querySelector('.friend-search-status')?.remove();
                            }
                        } else {
                            button.textContent = data.status === 'pending' ? 'Cancel Invite' : 'Added';
                            if (data.status === 'pending') {
                                button.disabled = false;
                                button.dataset.friendAction = 'cancel';
                                button.dataset.requestId = data.request_id || '';
                            }
                        }

                        if (row && summary && action !== 'cancel') {
                            row.classList.toggle('is-invite-sent', data.status === 'pending');
                            summary.querySelector('.friend-search-status')?.remove();
                            const confirmation = document.createElement('span');
                            confirmation.className = data.status === 'pending'
                                ? 'friend-search-status is-sent'
                                : 'friend-search-status is-friend';
                            confirmation.textContent = data.status === 'pending'
                                ? '✓ Invitation sent'
                                : 'Now friends';
                            summary.appendChild(confirmation);
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be added. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = action === 'accept'
                            ? 'Accept Invite'
                            : action === 'cancel'
                                ? 'Cancel Invite'
                                : 'Invite';
                    });
            });
        });
    }

    function renderRequests(requests) {
        if (!requestsList) {
            return;
        }

        if (!requests.length) {
            requestsList.innerHTML = '<p>No friend invites to approve.</p>';
            return;
        }

        requestsList.innerHTML = requests.map((request) => `
            <article class="friend-request-item" data-friend-request-id="${request.id}">
                ${renderAvatar(request.actor, request.name)}
                <div>
                    <strong>${escapeHtml(request.name)}</strong>
                    <span>Level ${escapeHtml(request.level || 1)}${request.title ? ` &middot; ${escapeHtml(request.title)}` : ''}</span>
                    <div class="friend-card-action-row">
                        <span class="friend-card-username">@${escapeHtml(request.username || '')}</span>
                        <div class="friend-card-action-buttons">
                            <button type="button" data-request-id="${request.id}" data-response="accepted">Approve</button>
                            <button type="button" data-request-id="${request.id}" data-response="declined">Decline</button>
                        </div>
                    </div>
                </div>
                <a class="friend-card-link" href="profile.php?id=${encodeURIComponent(request.user_id || '')}" aria-label="View ${escapeHtml(request.name)}'s profile"></a>
            </article>
        `).join('');

        if (!hasFocusedRequest && requestedFocusRequestId) {
            const focusedRequest = requestsList.querySelector(`[data-friend-request-id="${CSS.escape(requestedFocusRequestId)}"]`);
            if (focusedRequest) {
                hasFocusedRequest = true;
                focusNotificationTarget(focusedRequest);
            }
        }

        requestsList.querySelectorAll('button[data-request-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const response = button.dataset.response;
                const row = button.closest('.friend-request-item');
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = response === 'accepted' ? 'Approving...' : 'Declining...';

                postForm(userEndpoint('friend_respond.php'), {
                    csrf_token: csrfToken,
                    request_id: button.dataset.requestId,
                    response
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend invite could not be updated.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = response === 'accepted' ? 'Approve' : 'Decline';
                            return;
                        }

                        showStatus(data.message || 'Friend invite updated.', false);
                        if (data.xp_reward && window.craftcrawlShowXpReward) {
                            window.craftcrawlShowXpReward(data.xp_reward);
                        }
                        button.classList.remove('is-loading');
                        if (row) {
                            row.remove();
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend invite could not be updated.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = response === 'accepted' ? 'Approve' : 'Decline';
                    });
            });
        });
    }

    function renderSentRequests(requests) {
        if (!sentRequestsList) {
            return;
        }

        if (!requests.length) {
            sentRequestsList.innerHTML = '';
            sentRequestsList.hidden = true;
            return;
        }

        sentRequestsList.innerHTML = requests.map((request) => `
            <article class="friend-search-result is-invite-sent" data-sent-request-id="${request.id}">
                ${renderAvatar(request.actor, request.name)}
                <div class="friend-search-summary">
                    <strong>${escapeHtml(request.name)}</strong>
                    <span class="friend-search-meta">Level ${escapeHtml(request.level || 1)}${request.title ? ` &middot; ${escapeHtml(request.title)}` : ''}</span>
                    <div class="friend-card-action-row">
                        <span class="friend-search-username">@${escapeHtml(request.username || '')}</span>
                        <button type="button" data-request-id="${request.id}" data-friend-action="cancel">Cancel Invite</button>
                    </div>
                    <span class="friend-search-status is-sent">✓ Invitation sent</span>
                </div>
                <a class="friend-card-link" href="profile.php?id=${encodeURIComponent(request.user_id || '')}" aria-label="View ${escapeHtml(request.name)}'s profile"></a>
            </article>
        `).join('');
        sentRequestsList.hidden = Boolean(input?.value.trim());

        sentRequestsList.querySelectorAll('button[data-friend-action="cancel"]').forEach((button) => {
            button.addEventListener('click', () => {
                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = 'Canceling...';

                postForm(userEndpoint('friend_cancel.php'), {
                    csrf_token: csrfToken,
                    request_id: button.dataset.requestId
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend invite could not be canceled.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = 'Cancel Invite';
                            return;
                        }

                        showStatus(data.message || 'Friend invite canceled.', false);
                        button.closest('[data-sent-request-id]')?.remove();
                        if (!sentRequestsList.querySelector('[data-sent-request-id]')) {
                            sentRequestsList.hidden = true;
                        }
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend invite could not be canceled. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = 'Cancel Invite';
                    });
            });
        });
    }

    function renderCurrentFriends(friends) {
        if (!currentFriendsList) {
            return;
        }

        if (!friends.length) {
            currentFriendsList.innerHTML = '<p>No friends added yet.</p>';
            return;
        }

        const query = (currentFriendsFilter?.value || '').trim().toLowerCase();
        const visibleFriends = query
            ? friends.filter((friend) => {
                const name = String(friend.name || '').toLowerCase();
                const username = String(friend.username || '').toLowerCase();
                return name.includes(query) || username.includes(query);
            })
            : friends;

        if (!visibleFriends.length) {
            currentFriendsList.innerHTML = '<p>No friends match that search.</p>';
            return;
        }

        currentFriendsList.innerHTML = visibleFriends.map((friend) => `
            <article class="friend-current-item" data-friend-id="${friend.id}">
                ${renderAvatar(friend.actor, friend.name)}
                <div class="friend-current-summary">
                    <div class="friend-current-name-row">
                        <strong>${escapeHtml(friend.name)}</strong>
                        ${friend.is_new ? '<span class="friend-current-new-badge">New</span>' : ''}
                    </div>
                    <p class="friend-current-meta">Level ${escapeHtml(friend.level || 1)}${friend.title ? ` &middot; ${escapeHtml(friend.title)}` : ''}</p>
                    <div class="friend-current-action-row">
                        <p class="friend-current-meta friend-current-username">@${escapeHtml(friend.username || '')}</p>
                        <button type="button" class="danger-button friend-remove-button" data-remove-friend-id="${friend.id}" data-remove-friend-name="${escapeHtml(friend.name)}">Remove</button>
                    </div>
                </div>
                <a class="friend-card-link" href="profile.php?id=${encodeURIComponent(friend.id)}" aria-label="View ${escapeHtml(friend.name)}'s profile"></a>
            </article>
        `).join('');

        if (!hasFocusedFriend && requestedFocusFriendId) {
            const focusedFriend = currentFriendsList.querySelector(`[data-friend-id="${CSS.escape(requestedFocusFriendId)}"]`);
            if (focusedFriend) {
                hasFocusedFriend = true;
                focusNotificationTarget(focusedFriend);
            }
        }

        currentFriendsList.querySelectorAll('[data-remove-friend-id]').forEach((button) => {
            button.addEventListener('click', () => {
                const friendName = button.dataset.removeFriendName || 'this friend';

                if (!window.confirm(`Remove ${friendName} from your friends?`)) {
                    return;
                }

                button.disabled = true;
                button.classList.add('is-loading');
                button.textContent = 'Removing...';

                postForm(userEndpoint('friend_remove.php'), {
                    csrf_token: csrfToken,
                    friend_id: button.dataset.removeFriendId
                })
                    .then((data) => {
                        if (!data.ok) {
                            showStatus(data.message || 'Friend could not be removed.', true);
                            button.disabled = false;
                            button.classList.remove('is-loading');
                            button.textContent = 'Remove';
                            return;
                        }

                        showStatus(data.message || 'Friend removed.', false);
                        button.classList.remove('is-loading');
                        refreshFriendsData();
                    })
                    .catch(() => {
                        showStatus('Friend could not be removed. Please try again.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = 'Remove';
                    });
            });
        });
    }

    function renderFeedPollOptions(itemKey, options) {
        const buttons = options.map(function (opt) {
            return '<button type="button" class="business-poll-option-btn" data-feed-poll-vote'
                + ' data-item-key="' + escapeHtml(itemKey) + '" data-option-id="' + opt.id + '">'
                + escapeHtml(opt.option_text) + '</button>';
        }).join('');
        return '<div class="business-poll-vote-options">' + buttons + '</div>';
    }

    function renderFeedPollResults(options, userVotedOptionId, totalVotes) {
        const items = options.map(function (opt) {
            const pct = totalVotes > 0 ? Math.round((opt.vote_count / totalVotes) * 100) : 0;
            const isVoted = opt.id === userVotedOptionId;
            return '<div class="business-poll-result' + (isVoted ? ' is-voted' : '') + '">'
                + '<div class="business-poll-bar-btn">'
                + '<div class="business-poll-bar-fill" style="width:' + pct + '%"></div>'
                + '<span class="business-poll-bar-label">' + escapeHtml(opt.option_text) + '</span>'
                + '</div>'
                + '<span class="business-poll-bar-pct">' + pct + '%</span>'
                + '</div>';
        }).join('');
        return '<div class="business-poll-results" data-poll-results>'
            + items
            + '<p class="business-poll-total">' + totalVotes + ' vote' + (totalVotes !== 1 ? 's' : '') + '</p>'
            + '</div>';
    }

    function buildRewardSummary(item) {
        const parts = [];
        if (item.linked_badges && item.linked_badges.length) {
            item.linked_badges.forEach(function (b) { parts.push('Earned ' + b.name); });
        }
        if (item.linked_quests && item.linked_quests.length) {
            item.linked_quests.forEach(function (q) { parts.push('Completed ' + q.name); });
        }
        const totalXp = ((item.linked_badges || []).reduce(function (s, b) { return s + b.xp; }, 0))
            + ((item.linked_quests || []).reduce(function (s, q) { return s + q.xp; }, 0));
        if (totalXp > 0) {
            parts.push('+' + totalXp + ' XP');
        }
        return parts.join(' · ');
    }

    function renderCaptionArea(item) {
        const actorName = item.is_self ? 'You' : item.friend_name;
        const caption = item.caption || '';
        const rewardSummary = buildRewardSummary(item);
        const hasCaption = caption.length > 0;
        const hasRewards = rewardSummary.length > 0;

        if (!hasCaption && !hasRewards) {
            return '';
        }

        const unreadReactions = Number(item.unread_reaction_count || 0);
        const unreadComments = Number(item.unread_comment_count || 0);
        const totalUnread = unreadReactions + unreadComments;
        const unreadBadge = totalUnread > 0
            ? '<span class="feed-caption-unread-badge">' + (totalUnread > 9 ? '9+' : totalUnread) + '</span>'
            : '';

        const captionParts = [];
        if (hasCaption) {
            captionParts.push(escapeHtml(caption));
        }
        if (hasRewards) {
            captionParts.push('<span class="feed-reward-line">' + escapeHtml(rewardSummary) + '</span>');
        }
        const fullText = captionParts.join(' ');
        const inlinePreview = '<strong>' + escapeHtml(actorName) + '</strong> ' + fullText;

        const reactionPanel = renderReactionPanel(item);

        return `
            <div class="feed-caption-area" data-feed-caption>
                <div class="feed-caption-collapsed" data-feed-caption-collapsed>
                    <span class="feed-caption-text">${inlinePreview}</span>
                    <button type="button" class="feed-caption-more" data-feed-caption-more>more${unreadBadge}</button>
                </div>
                <div class="feed-caption-expanded" data-feed-caption-expanded hidden>
                    <p><strong>${escapeHtml(actorName)}</strong> ${hasCaption ? escapeHtml(caption) : ''}</p>
                    ${hasRewards ? '<p class="feed-reward-line">' + escapeHtml(rewardSummary) + '</p>' : ''}
                    ${reactionPanel}
                </div>
            </div>
        `;
    }

    function renderFeedItem(item) {
        const date = formatDate(item.created_at);
        const actions = renderFeedActions(item);
        const actorName = item.is_self ? 'You' : item.friend_name;

        if (item.type === 'level_up') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Reached Level ${escapeHtml(item.level)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.title)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'badge_earned') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Earned the badge ${escapeHtml(item.badge_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.badge_description)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'quest_complete') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Completed ${escapeHtml(item.quest_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(capitalize(item.period_type))} quest · ${escapeHtml(item.quest_description)} · +${escapeHtml(item.xp_awarded)} XP</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'quest_sweep') {
            const periodLabel = item.period_type === 'weekly' ? 'weekly' : 'daily';
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Completed all ${escapeHtml(periodLabel)} quests</strong>
                        <p class="feed-item-detail">${escapeHtml(item.quest_count)} quests cleared · +${escapeHtml(item.xp_awarded)} XP</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'event_want') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">${escapeHtml(item.is_self ? 'Want' : 'Wants')} to go to ${escapeHtml(item.event_name)}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.business_name)} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                        ${renderFeedDetailLinkRow(item)}
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'location_want') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">${escapeHtml(item.is_self ? 'Want' : 'Wants')} to visit ${renderBusinessLink(item)}</strong>
                        <p class="feed-item-detail">${escapeHtml(formatBusinessType(item.business_type))} · ${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'user_post') {
            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <p class="feed-user-post-body">${escapeHtml(item.body || '')}</p>
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'business_post') {
            const isPoll = item.post_type === 'poll';
            let pollSection = '';

            if (isPoll) {
                let pollContent = '';
                if (item.user_voted_option_id != null) {
                    pollContent = renderFeedPollResults(item.options || [], item.user_voted_option_id, item.total_votes || 0);
                } else if (item.is_expired) {
                    pollContent = '<p class="business-poll-expiry is-closed">Poll closed</p>';
                } else {
                    pollContent = renderFeedPollOptions(item.item_key, item.options || []);
                }
                pollSection = '<div data-feed-poll-section>' + pollContent + '</div>';
            }

            return `
                <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                    <div class="friends-feed-icon">${isPoll ? '📊' : '📢'}</div>
                    <div class="feed-item-content">
                        <p class="feed-item-meta">
                            <span>${renderBusinessLink(item)}</span>
                            ${date ? `<span aria-hidden="true">·</span><time>${escapeHtml(date)}</time>` : ''}
                        </p>
                        <strong class="feed-item-title">${escapeHtml(item.title)}</strong>
                        ${item.body ? `<p class="feed-item-detail feed-business-post-body">${escapeHtml(item.body)}</p>` : ''}
                        ${pollSection}
                        ${actions}
                    </div>
                </article>
            `;
        }

        if (item.type === 'checkin') {
            const visitLabel = item.visit_type === 'first_time' ? ' for the first time' : '';
            const photoHtml = item.photo_url
                ? `<div class="feed-checkin-photo"><img src="${escapeHtml(item.photo_url)}" alt="Check-in photo at ${escapeHtml(item.business_name)}"></div>`
                : '';
            const captionArea = renderCaptionArea(item);
            const checkinActions = renderCheckinActions(item);
            return `
                <article class="${feedItemClasses(item, 'feed-checkin-item')}" ${feedItemAttrs(item)}>
                    ${renderAvatar(item.actor, item.friend_name)}
                    <div class="feed-item-content">
                        ${renderFeedMeta(actorName, date)}
                        <strong class="feed-item-title">Checked in at ${renderBusinessLink(item)}${visitLabel}</strong>
                        <p class="feed-item-detail">${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                    </div>
                    ${photoHtml}
                    <div class="feed-checkin-below">
                        ${checkinActions}
                        ${captionArea}
                    </div>
                </article>
            `;
        }

        return `
            <article class="${feedItemClasses(item)}" ${feedItemAttrs(item)}>
                ${renderAvatar(item.actor, item.friend_name)}
                <div class="feed-item-content">
                    ${renderFeedMeta(actorName, date)}
                    <strong class="feed-item-title">Visited ${renderBusinessLink(item)} for the first time</strong>
                    <p class="feed-item-detail">${escapeHtml(item.city)}, ${escapeHtml(item.state)}</p>
                    ${actions}
                </div>
            </article>
        `;
    }

    function renderFeed(data) {
        const friends = data.friends || [];
        const items = data.feed || [];
        if (data.feed_seen_at) {
            feedSeenAt = data.feed_seen_at;
        }
        currentFriendsCache = friends;

        if (count) {
            count.textContent = friends.length === 1 ? '1 friend' : `${friends.length} friends`;
        }

        renderCurrentFriends(friends);

        if (!feed) {
            return;
        }

        if (!items.length) {
            feed.innerHTML = friends.length
                ? '<p>No friend activity yet.</p>'
                : '<p>Add friends to see level-ups and first-time visits here.</p>';
            hasMore = false;
            nextFeedCursor = { before: null, key: null };
            feedPaginationFailed = false;
            updateFeedPagingDebug(0);
            updateFeedSentinel(false);
            return;
        }

        feed.innerHTML = items.map(renderFeedItem).join('');

        focusFeedItemIfRequested();
        nextFeedCursor = feedCursorFromResponse(data, items);
        hasMore = feedPageMayHaveMore(data, items);
        feedPaginationFailed = false;
        updateFeedPagingDebug(items.length);
        updateFeedSentinel(hasMore);
        window.requestAnimationFrame(checkFeedNearBottom);

        playPendingThreadReturnAnchor();
    }

    function takeThreadReturnItemKey() {
        try {
            const itemKey = sessionStorage.getItem(threadReturnStorageKey) || '';
            if (itemKey) {
                sessionStorage.removeItem(threadReturnStorageKey);
            }
            return itemKey;
        } catch (_) {
            return '';
        }
    }

    function clearThreadReturnItemKey() {
        try {
            sessionStorage.removeItem(threadReturnStorageKey);
        } catch (_) {}
    }

    function queueThreadReturnAnchor(itemKey) {
        if (!itemKey) return;

        feedThreadPendingReturnItemKey = itemKey;
        feedThreadPendingReturnUntil = Date.now() + 1700;
        [140, 360, 760].forEach((delay) => {
            window.setTimeout(playPendingThreadReturnAnchor, delay);
        });
    }

    function playPendingThreadReturnAnchor() {
        if (!feedThreadPendingReturnItemKey) {
            const storedItemKey = takeThreadReturnItemKey();
            if (storedItemKey) {
                queueThreadReturnAnchor(storedItemKey);
            }
            return;
        }

        if (Date.now() > feedThreadPendingReturnUntil) {
            feedThreadPendingReturnItemKey = '';
            feedThreadPendingReturnUntil = 0;
            return;
        }

        animateThreadReturnAnchor(0, feedThreadPendingReturnItemKey);
    }

    function animateThreadReturnAnchor(attempt = 0, itemKey = '') {
        const returnItemKey = itemKey;
        if (!feed || !returnItemKey) {
            return;
        }

        const play = () => {
            const item = feed.querySelector(`[data-feed-item-key="${CSS.escape(returnItemKey)}"]`);
            if (!item) {
                if (attempt < 4) {
                    window.setTimeout(() => animateThreadReturnAnchor(attempt + 1, returnItemKey), 120);
                }
                return;
            }

            const rect = item.getBoundingClientRect();
            const outOfView = rect.top < 76 || rect.bottom > window.innerHeight - 86;
            if (outOfView) {
                item.scrollIntoView({ block: 'center', behavior: 'auto' });
            }

            item.classList.remove('is-opening-thread');
            item.classList.add('has-thread-return-highlight');
            if (item.querySelector('.feed-return-highlight')) {
                return;
            }
            const highlight = document.createElement('span');
            highlight.className = 'feed-return-highlight';
            highlight.setAttribute('aria-hidden', 'true');
            highlight.innerHTML = '<span class="feed-return-highlight-rail"></span><span class="feed-return-highlight-rail"></span>';
            item.appendChild(highlight);

            const rails = Array.from(highlight.querySelectorAll('.feed-return-highlight-rail'));
            if (typeof highlight.animate === 'function') {
                rails.forEach((rail) => {
                    rail.animate([
                        { opacity: 0, transform: 'scaleX(.92)' },
                        { opacity: 1, transform: 'scaleX(1)', offset: 0.45 },
                        { opacity: 0, transform: 'scaleX(.92)' }
                    ], {
                        duration: 1000,
                        easing: 'ease-in-out',
                        fill: 'both'
                    });
                });
            } else {
                highlight.classList.add('is-animating');
            }

            window.setTimeout(() => {
                highlight.remove();
                item.classList.remove('has-thread-return-highlight');
                if (returnItemKey === feedThreadPendingReturnItemKey && Date.now() > feedThreadPendingReturnUntil) {
                    feedThreadPendingReturnItemKey = '';
                    feedThreadPendingReturnUntil = 0;
                }
            }, 1050);
        };

        window.setTimeout(play, attempt === 0 ? 40 : 0);
    }

    document.addEventListener('craftcrawl:user-shell-navigated', (event) => {
        const url = new URL(event.detail?.url || window.location.href, window.location.href);
        if (url.pathname.endsWith('/feed.php')) {
            playPendingThreadReturnAnchor();
        }
    });

    function feedThreadUrl(itemKey) {
        return `feed_post.php?item=${encodeURIComponent(itemKey)}`;
    }

    function normalizeFeedThreadUrl(url) {
        return new URL(url, window.location.href).href;
    }

    async function fetchFeedThreadPage(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'CraftCrawlShell' }
        });
        if (!response.ok) throw new Error('Thread could not be loaded.');
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const page = doc.querySelector('.feed-thread-page');
        if (!page) throw new Error('Thread content was missing.');
        return page;
    }

    function ensureFeedThreadOverlay() {
        if (feedThreadOverlayState.overlay && !feedThreadOverlayState.overlay.isConnected) {
            feedThreadOverlayState.overlay = null;
            feedThreadOverlayState.content = null;
        }

        if (feedThreadOverlayState.overlay) {
            return feedThreadOverlayState.overlay;
        }

        const existingOverlay = document.querySelector('[data-feed-thread-overlay]');
        if (existingOverlay) {
            feedThreadOverlayState.overlay = existingOverlay;
            feedThreadOverlayState.content = existingOverlay.querySelector('[data-feed-thread-overlay-content]');
            return feedThreadOverlayState.overlay;
        }

        feedThreadOverlayState.overlay = document.createElement('div');
        feedThreadOverlayState.overlay.className = 'feed-thread-overlay';
        feedThreadOverlayState.overlay.setAttribute('data-feed-thread-overlay', '');
        feedThreadOverlayState.overlay.hidden = true;
        feedThreadOverlayState.overlay.innerHTML = '<div class="feed-thread-overlay-scrim" data-feed-thread-overlay-close></div><div class="feed-thread-overlay-content" data-feed-thread-overlay-content></div>';
        document.body.appendChild(feedThreadOverlayState.overlay);
        feedThreadOverlayState.content = feedThreadOverlayState.overlay.querySelector('[data-feed-thread-overlay-content]');

        feedThreadOverlayState.overlay.addEventListener('click', (event) => {
            if (event.target.closest('[data-feed-thread-overlay-close]')) {
                closeFeedThreadOverlay({ useHistory: true });
            }
        });

        return feedThreadOverlayState.overlay;
    }

    function setFeedThreadOverlayContent(page) {
        const overlay = ensureFeedThreadOverlay();
        const overlayContent = feedThreadOverlayState.content;
        overlayContent.style.transform = '';
        overlayContent.style.opacity = '';
        page.classList.add('feed-thread-page-entering');
        overlayContent.replaceChildren(page);
        window.setTimeout(() => page.classList.remove('feed-thread-page-entering'), 420);
        window.CraftCrawlInitFeedThread?.(overlay);
    }

    function resetFeedThreadOverlayMotion() {
        if (feedThreadOverlayState.overlay) {
            feedThreadOverlayState.overlay.classList.remove('is-swipe-dragging', 'is-swipe-dismissing');
        }
        if (feedThreadOverlayState.content) {
            feedThreadOverlayState.content.style.transform = '';
            feedThreadOverlayState.content.style.opacity = '';
            feedThreadOverlayState.content.classList.remove('is-swipe-scroll-locked');
            delete feedThreadOverlayState.content.dataset.feedSwipeScrollTop;
        }
    }

    async function refreshFeedThreadOverlay(url) {
        if (!feedThreadOverlayState.overlay || feedThreadOverlayState.overlay.hidden) {
            return false;
        }
        const page = await fetchFeedThreadPage(normalizeFeedThreadUrl(url));
        setFeedThreadOverlayContent(page);
        return true;
    }

    function destroyFeedThreadOverlay(options = {}) {
        window.clearTimeout(feedThreadOverlayState.closeTimer);
        const overlays = Array.from(document.querySelectorAll('[data-feed-thread-overlay]'));
        overlays.forEach((overlay) => {
            overlay.hidden = true;
            overlay.remove();
        });
        feedThreadOverlayState.overlay = null;
        feedThreadOverlayState.content = null;
        feedThreadOverlayState.itemKey = '';
        feedThreadOverlayState.baseUrl = '';
        feedThreadOverlayState.closeTimer = 0;
        document.body.classList.remove('feed-thread-overlay-open', 'feed-comment-composer-open');
        document.documentElement.classList.remove('feed-thread-open-requested');
        document.documentElement.style.removeProperty('--feed-compose-keyboard-offset');
        if (!options.skipReturnAnchor) {
            playPendingThreadReturnAnchor();
        }
        return overlays.length > 0;
    }

    function closeFeedThreadOverlay(options = {}) {
        const overlay = feedThreadOverlayState.overlay;
        const overlayContent = feedThreadOverlayState.content;
        if (!overlay || overlay.hidden) {
            return false;
        }
        if (overlay.classList.contains('is-closing')) {
            return true;
        }

        const itemKey = options.returnItemKey || feedThreadOverlayState.itemKey;
        if (itemKey && !options.skipReturnAnchor) {
            clearThreadReturnItemKey();
            queueThreadReturnAnchor(itemKey);
        }
        window.clearTimeout(feedThreadOverlayState.closeTimer);
        resetFeedThreadOverlayMotion();
        overlay.classList.add('is-closing');
        document.body.classList.remove('feed-thread-overlay-open');

        let closeFinished = false;
        const finishClose = (event = null) => {
            if (event && event.target !== overlayContent) {
                return;
            }
            if (closeFinished) {
                return;
            }
            closeFinished = true;
            window.clearTimeout(feedThreadOverlayState.closeTimer);
            overlay.hidden = true;
            overlay.classList.remove('is-open', 'is-closing', 'is-swipe-dragging', 'is-swipe-dismissing');
            document.documentElement.style.removeProperty('--feed-compose-keyboard-offset');
            document.body.classList.remove('feed-comment-composer-open');
            resetFeedThreadOverlayMotion();
            overlayContent?.replaceChildren();
            feedThreadOverlayState.itemKey = '';
            playPendingThreadReturnAnchor();
        };

        if (options.immediate) {
            finishClose();
        } else {
            feedThreadOverlayState.closeTimer = window.setTimeout(finishClose, 140);
            overlayContent?.addEventListener('animationend', finishClose, { once: true });
        }

        if (options.useHistory && history.state?.craftcrawlFeedThreadOverlay) {
            history.back();
        }

        return true;
    }

    async function openFeedThreadOverlay(itemKey, options = {}) {
        if (!itemKey) return false;

        const feedItem = feed?.querySelector(`[data-feed-item-key="${CSS.escape(itemKey)}"]`);
        const targetUrl = normalizeFeedThreadUrl(feedThreadUrl(itemKey));
        if (feedItem) {
            feedItem.classList.add('is-opening-thread');
        }

        try {
            ensureFeedThreadOverlay();
            const overlay = feedThreadOverlayState.overlay;
            const overlayContent = feedThreadOverlayState.content;
            overlay.classList.remove('is-closing', 'is-swipe-dragging', 'is-swipe-dismissing');
            overlayContent.style.transform = '';
            overlayContent.style.opacity = '';
            const page = await fetchFeedThreadPage(targetUrl);
            feedThreadOverlayState.itemKey = itemKey;
            feedThreadOverlayState.baseUrl = window.location.href;
            setFeedThreadOverlayContent(page);
            overlay.hidden = false;
            overlay.classList.add('is-open');
            document.body.classList.add('feed-thread-overlay-open');
            feedItem?.classList.remove('is-opening-thread');

            if (options.updateHistory !== false) {
                history.pushState({ craftcrawlFeedThreadOverlay: true, itemKey }, '', targetUrl);
            }
            return true;
        } catch (_) {
            feedItem?.classList.remove('is-opening-thread');
            window.location.href = targetUrl;
            return true;
        }
    }

    window.CraftCrawlOpenFeedThreadOverlay = openFeedThreadOverlay;
    window.CraftCrawlCloseFeedThreadOverlay = closeFeedThreadOverlay;
    window.CraftCrawlDestroyFeedThreadOverlay = destroyFeedThreadOverlay;
    window.CraftCrawlRefreshFeedThreadOverlay = refreshFeedThreadOverlay;

    window.addEventListener('popstate', () => {
        const overlay = feedThreadOverlayState.overlay;
        if (overlay && !overlay.hidden && !history.state?.craftcrawlFeedThreadOverlay) {
            closeFeedThreadOverlay({ useHistory: false });
        }
    });

    window.addEventListener('pageshow', () => {
        const overlay = feedThreadOverlayState.overlay;
        if (!overlay || overlay.hidden) {
            return;
        }
        if (!history.state?.craftcrawlFeedThreadOverlay) {
            closeFeedThreadOverlay({ useHistory: false, immediate: true });
            return;
        }
        resetFeedThreadOverlayMotion();
    });

    // Delegated poll vote handler for feed items
    if (feed) {
        const reactionSwipe = {
            active: false,
            pointerId: null,
            startX: 0,
            lastX: 0,
            panel: null,
            track: null,
            page: 0,
            pageWidth: 0
        };

        feed.addEventListener('pointerdown', function (event) {
            const swipe = event.target.closest('[data-reaction-swipe]');
            const panel = swipe?.closest('[data-reaction-disclosure-panel]');
            const track = panel?.querySelector('[data-reaction-track]');

            if (!swipe || !panel || !track || track.children.length < 2) {
                return;
            }

            reactionSwipe.active = true;
            reactionSwipe.pointerId = event.pointerId;
            reactionSwipe.startX = event.clientX;
            reactionSwipe.lastX = event.clientX;
            reactionSwipe.panel = panel;
            reactionSwipe.track = track;
            reactionSwipe.page = Number(panel.dataset.reactionPage || 0);
            reactionSwipe.pageWidth = swipe.clientWidth || panel.clientWidth || 1;
            track.style.transition = 'none';
            swipe.setPointerCapture?.(event.pointerId);
        });

        feed.addEventListener('pointermove', function (event) {
            if (!reactionSwipe.active || event.pointerId !== reactionSwipe.pointerId || !reactionSwipe.track) {
                return;
            }

            const deltaX = event.clientX - reactionSwipe.startX;
            reactionSwipe.lastX = event.clientX;
            reactionSwipe.track.style.transform = `translateX(${(-reactionSwipe.page * reactionSwipe.pageWidth) + deltaX}px)`;
        });

        function finishReactionSwipe(event) {
            if (!reactionSwipe.active || event.pointerId !== reactionSwipe.pointerId || !reactionSwipe.panel || !reactionSwipe.track) {
                return;
            }

            const deltaX = event.clientX - reactionSwipe.startX;
            const threshold = Math.min(90, reactionSwipe.pageWidth * 0.22);
            let nextPage = reactionSwipe.page;

            if (deltaX <= -threshold) {
                nextPage += 1;
            } else if (deltaX >= threshold) {
                nextPage -= 1;
            }

            setReactionPage(reactionSwipe.panel, nextPage);
            reactionSwipe.active = false;
            reactionSwipe.pointerId = null;
            reactionSwipe.panel = null;
            reactionSwipe.track = null;
        }

        feed.addEventListener('pointerup', finishReactionSwipe);
        feed.addEventListener('pointercancel', finishReactionSwipe);

        feed.addEventListener('click', function (event) {
            const interactiveTarget = event.target.closest('a, button, input, textarea, select, label, [role="button"], [data-reaction-disclosure], [data-feed-poll-section], [data-feed-caption]');
            const feedItem = event.target.closest('.friends-feed-item[data-feed-item-key]');
            if (feedItem && !interactiveTarget && feed.contains(feedItem)) {
                const itemKey = feedItem.dataset.feedItemKey || '';
                if (!itemKey) {
                    return;
                }

                event.preventDefault();
                openFeedThreadOverlay(itemKey);
                return;
            }

            const reactionToggle = event.target.closest('[data-reaction-disclosure-toggle]');
            if (reactionToggle) {
                const disclosure = reactionToggle.closest('[data-reaction-disclosure]');
                const article = reactionToggle.closest('article');
                const panel = article?.querySelector(`[data-reaction-disclosure-panel][data-item-key="${CSS.escape(disclosure?.dataset.itemKey || '')}"]`);

                if (!disclosure || !panel) {
                    return;
                }

                const isExpanded = reactionToggle.getAttribute('aria-expanded') === 'true';
                reactionToggle.setAttribute('aria-expanded', String(!isExpanded));
                panel.hidden = isExpanded;
                if (!isExpanded) {
                    setReactionPage(panel, Number(panel.dataset.reactionPage || 0), false);
                    highlightUnreadReactionEntries(panel);
                }

                if (!isExpanded && (Number(disclosure.dataset.unreadCount || 0) > 0 || disclosure.querySelector('.feed-action-unread-badge'))) {
                    markReactionNotificationsSeen(disclosure);
                }
                return;
            }

            const moreButton = event.target.closest('[data-feed-caption-more]');
            if (moreButton) {
                const captionArea = moreButton.closest('[data-feed-caption]');
                const collapsed = captionArea?.querySelector('[data-feed-caption-collapsed]');
                const expanded = captionArea?.querySelector('[data-feed-caption-expanded]');
                if (collapsed && expanded) {
                    collapsed.hidden = true;
                    expanded.hidden = false;
                    const article = moreButton.closest('article');
                    const panel = expanded.querySelector('[data-reaction-disclosure-panel]');
                    if (panel) {
                        setReactionPage(panel, 0, false);
                        highlightUnreadReactionEntries(panel);
                        const itemKey = panel.dataset.itemKey || '';
                        if (itemKey) {
                            const fakeDisclosure = { dataset: { itemKey: itemKey, unreadCount: '0' }, querySelector: function () { return null; } };
                            markReactionNotificationsSeen(fakeDisclosure);
                        }
                    }
                }
                return;
            }

            const voteBtn = event.target.closest('[data-feed-poll-vote]');
            if (!voteBtn || voteBtn.disabled) {
                return;
            }

            const pollSection = voteBtn.closest('[data-feed-poll-section]');
            if (!pollSection) {
                return;
            }

            pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                btn.disabled = true;
            });

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('post_id', voteBtn.dataset.itemKey.split(':')[1]);
            formData.append('option_id', voteBtn.dataset.optionId);

            fetch(userEndpoint('business_poll_vote.php'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) {
                        pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                            btn.disabled = false;
                        });
                        return;
                    }
                    pollSection.innerHTML = renderFeedPollResults(
                        data.options,
                        data.user_voted_option_id,
                        data.total_votes
                    );
                })
                .catch(function () {
                    pollSection.querySelectorAll('[data-feed-poll-vote]').forEach(function (btn) {
                        btn.disabled = false;
                    });
                });
        });
    }

    function loadMore() {
        if (!feed || !nextFeedCursor.before || !nextFeedCursor.key || !hasMore || loadingMore || feedPaginationFailed) {
            return;
        }

        loadingMore = true;
        showFeedSentinelLoading();

        const params = new URLSearchParams({
            before: nextFeedCursor.before,
            before_key: nextFeedCursor.key
        });

        window.setTimeout(() => {
            fetch(`${userEndpoint('friends_feed.php')}?${params.toString()}`, { credentials: 'same-origin', cache: 'no-store' })
            .then((r) => r.json())
            .then((data) => {
                if (!data.ok || !data.feed || !data.feed.length) {
                    hasMore = false;
                    return;
                }

                data.feed.forEach((item) => {
                    const div = document.createElement('div');
                    div.innerHTML = renderFeedItem(item).trim();
                    const article = div.firstElementChild;
                    if (article) {
                        feed.appendChild(article);
                    }
                });

                focusFeedItemIfRequested();
                nextFeedCursor = feedCursorFromResponse(data, data.feed);
                hasMore = feedPageMayHaveMore(data, data.feed);
                feedPaginationFailed = false;
                updateFeedPagingDebug(data.feed.length);
            })
            .catch((error) => {
                feedPaginationFailed = true;
                hasMore = false;
                console.warn('Friends feed pagination failed.', error);
            })
            .finally(() => {
                loadingMore = false;
                updateFeedSentinel(hasMore);
                checkFeedNearBottom();
            });
        }, feedPaginationDelayMs);
    }

    function feedCursorFromResponse(data, items) {
        const lastItem = items[items.length - 1] || {};

        return {
            before: data.next_before || lastItem.created_at || null,
            key: data.next_before_key || lastItem.item_key || null
        };
    }

    function feedPageMayHaveMore(data, items) {
        return data.has_more === true || items.length >= feedPageSize;
    }

    function updateFeedPagingDebug(pageItemCount) {
        if (!feed) {
            return;
        }

        feed.dataset.feedHasMore = hasMore ? 'true' : 'false';
        feed.dataset.feedLastPageCount = String(pageItemCount);
        feed.dataset.feedNextBefore = nextFeedCursor.before || '';
        feed.dataset.feedNextBeforeKey = nextFeedCursor.key || '';
    }

    function ensureFeedObserver() {
        if (feedObserver || !feed) {
            return feedObserver;
        }

        feedObserver = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                loadMore();
            }
        }, { rootMargin: '0px', threshold: 0 });

        return feedObserver;
    }

    function removeFeedSentinel() {
        if (!feedSentinel) {
            return;
        }

        feedObserver?.unobserve(feedSentinel);
        feedSentinel.remove();
        feedSentinel = null;
    }

    function showFeedSentinelLoading() {
        if (!feedSentinel) {
            return;
        }

        feedObserver?.unobserve(feedSentinel);
        feedSentinel.classList.add('is-loading');
        feedSentinel.innerHTML = '<span aria-hidden="true"></span><strong>Loading more posts</strong>';
    }

    function updateFeedSentinel(show) {
        removeFeedSentinel();

        if (!show || !feed) {
            return;
        }

        feedSentinel = document.createElement('div');
        feedSentinel.setAttribute('data-feed-sentinel', '');
        feedSentinel.setAttribute('aria-hidden', 'true');
        feed.appendChild(feedSentinel);
        ensureFeedObserver()?.observe(feedSentinel);
    }

    function checkFeedNearBottom() {
        if (!feed || !feedSentinel || !hasMore || loadingMore || !isFeedTabActive()) {
            return;
        }

        const sentinelRect = feedSentinel.getBoundingClientRect();
        if (sentinelRect.top <= window.innerHeight && sentinelRect.bottom >= 0) {
            loadMore();
        }
    }

    if (feed) {
        window.addEventListener('scroll', checkFeedNearBottom, { passive: true });
        window.addEventListener('resize', checkFeedNearBottom);
    }

    function renderCheckinActions(item) {
        if (!item.item_key) {
            return '';
        }

        if (item.allow_interactions === false && !item.is_self) {
            return '';
        }

        const reactions = item.reactions || [];
        const reactionMap = {};
        const availableReactions = availableReactionTypes(item);

        reactions.forEach((reaction) => {
            reactionMap[reaction.type] = reaction;
        });

        return `
            <div class="feed-action-row feed-action-row-flat">
                <div class="feed-primary-actions">
                    ${renderCommentsLink(item)}
                </div>
                <div class="feed-reactions">
                    ${availableReactions.map((type) => {
                        const reaction = reactionMap[type] || { count: 0, reacted: false };
                        return `
                            <button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(item.item_key)}" data-reaction-type="${type}" aria-label="${escapeHtml(reactionTextLabels[type] || type)}">
                                ${reactionLabels[type]}${reaction.count > 0 ? `<span class="feed-reaction-count">${reaction.count}</span>` : ''}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    function renderFeedActions(item) {
        if (!item.item_key) {
            return '';
        }

        if (item.allow_interactions === false && item.type !== 'business_post' && !item.is_self) {
            return '';
        }

        const reactions = item.reactions || [];
        const reactionMap = {};
        const availableReactions = availableReactionTypes(item);

        reactions.forEach((reaction) => {
            reactionMap[reaction.type] = reaction;
        });

        return `
            <div class="feed-action-row">
                <div class="feed-primary-actions">
                    ${renderCommentsLink(item)}
                </div>
                ${renderReactionDisclosure(item)}
                <div class="feed-reactions">
                    ${availableReactions.map((type) => {
                        const reaction = reactionMap[type] || { count: 0, reacted: false };
                        return `
                            <button type="button" class="${reaction.reacted ? 'is-active' : ''}" data-feed-reaction data-item-key="${escapeHtml(item.item_key)}" data-reaction-type="${type}" aria-label="${escapeHtml(reactionTextLabels[type] || type)}">
                                ${reactionLabels[type]}${reaction.count > 0 ? ` ${reaction.count}` : ''}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
            ${renderReactionPanel(item)}
        `;
    }

    function renderCommentsLink(item) {
        if (!item.item_key) {
            return '';
        }

        const commentCount = Number(item.comment_count || 0);
        const unreadCount = Number(item.unread_comment_count || 0);
        const label = commentCount > 0 ? String(commentCount) : '';
        const unreadLabel = unreadCount > 9 ? '9+' : String(unreadCount);

        return `
            <a class="feed-comments-link" href="feed_post.php?item=${encodeURIComponent(item.item_key)}" aria-label="Show comments">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5a8.5 8.5 0 0 1 17 0Z"></path>
                </svg>
                ${label ? `<span>${label}</span>` : ''}
                ${unreadCount > 0 ? `<span class="feed-action-unread-badge">${unreadLabel}</span>` : ''}
            </a>
        `;
    }

    function renderReactionDisclosure(item) {
        const itemKey = item.item_key || '';
        const unreadCount = Number(item.unread_reaction_count || 0);
        const unreadLabel = unreadCount > 9 ? '9+' : String(unreadCount);
        const panelId = `feed-reaction-list-${String(itemKey).replace(/[^a-z0-9_-]/gi, '-')}`;

        return `
            <div class="feed-reaction-disclosure" data-reaction-disclosure data-item-key="${escapeHtml(itemKey)}" data-unread-count="${unreadCount}">
                <button type="button" class="feed-reaction-disclosure-toggle" data-reaction-disclosure-toggle aria-expanded="false" aria-controls="${escapeHtml(panelId)}">
                    <span>Reactions</span>
                    ${unreadCount > 0 ? `<span class="feed-action-unread-badge">${unreadLabel}</span>` : ''}
                    <span class="feed-reaction-disclosure-arrow" aria-hidden="true">⌄</span>
                </button>
            </div>
        `;
    }

    function renderReactionPanel(item, isExpanded = false) {
        const itemKey = item.item_key || '';
        const entries = Array.isArray(item.reaction_entries) ? item.reaction_entries : [];
        const panelId = `feed-reaction-list-${String(itemKey).replace(/[^a-z0-9_-]/gi, '-')}`;
        const pages = [];

        for (let index = 0; index < entries.length; index += reactionPageSize) {
            pages.push(entries.slice(index, index + reactionPageSize));
        }

        return `
            <div class="feed-reaction-list" id="${escapeHtml(panelId)}" data-reaction-disclosure-panel data-item-key="${escapeHtml(itemKey)}" data-reaction-page="0"${isExpanded ? '' : ' hidden'}>
                ${entries.length ? `
                    <div class="feed-reaction-swipe" data-reaction-swipe>
                        <div class="feed-reaction-track" data-reaction-track style="transform: translateX(0px);">
                            ${pages.map((page) => `
                                <div class="feed-reaction-page">
                                    ${page.map((entry) => `
                                        <div class="feed-reaction-list-item${entry.is_unread ? ' is-unread' : ''}" data-reaction-list-item data-reaction-type="${escapeHtml(entry.type || '')}" data-reactor-id="${escapeHtml(entry.user_id || '')}" data-reaction-unread="${entry.is_unread ? 'true' : 'false'}">
                                            <span class="feed-reaction-list-symbol">${escapeHtml(reactionLabels[entry.type] || '')}</span>
                                            <strong>${escapeHtml(entry.name || 'Someone')}</strong>
                                            ${entry.created_at ? `<time>${escapeHtml(formatDate(entry.created_at))}</time>` : ''}
                                        </div>
                                    `).join('')}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ${pages.length > 1 ? `<div class="feed-reaction-page-count" data-reaction-page-count>1 / ${pages.length}</div>` : ''}
                ` : '<p>No reactions yet.</p>'}
            </div>
        `;
    }

    function setReactionPage(panel, nextPage, animate = true) {
        const swipe = panel?.querySelector('[data-reaction-swipe]');
        const track = panel?.querySelector('[data-reaction-track]');
        const pageCount = track ? track.children.length : 0;

        if (!panel || !track || pageCount < 1) {
            return;
        }

        const page = Math.max(0, Math.min(pageCount - 1, nextPage));
        panel.dataset.reactionPage = String(page);
        track.style.transition = animate ? '' : 'none';
        track.style.transform = `translateX(${-page * (swipe?.clientWidth || panel.clientWidth || 1)}px)`;

        const count = panel.querySelector('[data-reaction-page-count]');
        if (count) {
            count.textContent = `${page + 1} / ${pageCount}`;
        }
    }

    function renderFeedDetailLink(item) {
        if (item.type === 'event_want') {
            return `<a class="feed-detail-link" href="../event_details.php?id=${encodeURIComponent(item.event_id)}&date=${encodeURIComponent(item.event_date)}">View Event</a>`;
        }

        return '';
    }

    function renderFeedDetailLinkRow(item) {
        const link = renderFeedDetailLink(item);

        return link ? `<div class="feed-detail-link-row">${link}</div>` : '';
    }

    function loadFeed() {
        return fetch(userEndpoint('friends_feed.php'), { credentials: 'same-origin', cache: 'no-store' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    showStatus(data.message || 'Friends feed could not be loaded.', true);
                    return;
                }

                renderFeed(data);
                loadStatus();
            })
            .catch(() => {
                showStatus('Friends feed could not be loaded.', true);
            });
    }

    function ensureFeedComposer() {
        if (!panel || panel.dataset.feedComposerReady === 'true') {
            return;
        }
        panel.dataset.feedComposerReady = 'true';
        document.querySelectorAll('[data-feed-compose-fab], [data-feed-compose-modal]').forEach((element) => {
            element.remove();
        });

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'feed-compose-fab';
        button.dataset.feedComposeFab = 'true';
        button.setAttribute('aria-label', 'Compose a feed post');
        button.innerHTML = `
            <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
            </svg>
        `;

        const overlay = document.createElement('div');
        overlay.className = 'feed-compose-modal';
        overlay.dataset.feedComposeModal = 'true';
        overlay.hidden = true;
        overlay.innerHTML = `
            <div class="feed-compose-modal-scrim" data-feed-compose-close></div>
            <form class="feed-compose-sheet" data-feed-compose-form>
                <div class="feed-compose-sheet-header">
                    <strong>New Post</strong>
                    <button type="button" data-feed-compose-close aria-label="Close composer">&times;</button>
                </div>
                <label for="feed-compose-body">Post</label>
                <textarea id="feed-compose-body" name="body" maxlength="360" rows="5" required placeholder="Share something with your friends"></textarea>
                <div class="feed-compose-sheet-footer">
                    <span data-feed-compose-count>360</span>
                    <button type="submit">Post</button>
                </div>
            </form>
        `;

        document.body.appendChild(button);
        document.body.appendChild(overlay);

        const formEl = overlay.querySelector('[data-feed-compose-form]');
        const textarea = overlay.querySelector('textarea');
        const counter = overlay.querySelector('[data-feed-compose-count]');

        function updateCount() {
            if (counter && textarea) {
                counter.textContent = String(360 - textarea.value.length);
            }
        }

        function openComposer() {
            overlay.hidden = false;
            document.body.classList.add('feed-compose-modal-open');
            updateCount();
            window.requestAnimationFrame(() => textarea?.focus());
        }

        function closeComposer() {
            overlay.hidden = true;
            document.body.classList.remove('feed-compose-modal-open');
            formEl?.reset();
            updateCount();
        }

        button.addEventListener('click', openComposer);
        overlay.querySelectorAll('[data-feed-compose-close]').forEach((closeButton) => {
            closeButton.addEventListener('click', closeComposer);
        });
        textarea?.addEventListener('input', updateCount);

        formEl?.addEventListener('submit', (event) => {
            event.preventDefault();
            const body = textarea?.value.trim() || '';
            if (!body) {
                textarea?.focus();
                return;
            }

            const submit = formEl.querySelector('button[type="submit"]');
            submit.disabled = true;
            postForm(userEndpoint('feed_post_create.php'), {
                csrf_token: csrfToken,
                body
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Post could not be created.', true);
                        return;
                    }
                    closeComposer();
                    return loadFeed().then(() => {
                        if (data.item_key) {
                            requestedFocusItemKey = data.item_key;
                            requestedFocusSection = '';
                            requestedFeedFocusSignature = [data.item_key, '', '', ''].join('|');
                            hasFocusedFeedItem = false;
                            focusFeedItemIfRequested();
                        }
                    });
                })
                .catch(() => {
                    showStatus('Post could not be created.', true);
                })
                .finally(() => {
                    submit.disabled = false;
                });
        });
    }

    function isFeedTabActive() {
        return Boolean(panel && !panel.closest('[data-user-tab-panel]')?.hidden);
    }

    function refreshVisibleFeed() {
        if (!isFeedTabActive()) {
            return Promise.resolve();
        }

        return loadFeed();
    }

    function hasRenderedFeedItems() {
        return Boolean(feed?.querySelector('.friends-feed-item[data-feed-item-key]'));
    }

    window.CraftCrawlRefreshFriendsFeed = refreshVisibleFeed;

    window.addEventListener('craftcrawl:event-want-updated', () => {
        loadFeed();
    });

    window.addEventListener('craftcrawl:user-tab-changed', (event) => {
        if (event.detail?.tab === 'feed') {
            updateFocusRequestFromUrl(event.detail?.url || window.location.href);
            if (!event.detail?.userInitiated && hasRenderedFeedItems()) {
                playPendingThreadReturnAnchor();
                return;
            }
            refreshVisibleFeed()
                .then(() => {
                    if (event.detail?.userInitiated) {
                        return markFriendsSeen('feed');
                    }

                    return null;
                });
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            loadStatus();
            if (managerPage) {
                loadRequests();
            }
            refreshVisibleFeed();
        }
    });

    function loadRequests() {
        if (!requestsList) {
            return Promise.resolve();
        }

        return fetch(userEndpoint('friend_requests.php'), { credentials: 'same-origin' })
            .then((response) => response.json())
            .then((data) => {
                if (!data.ok) {
                    showStatus(data.message || 'Friend invites could not be loaded.', true);
                    return;
                }

                renderRequests(data.requests || []);
                renderSentRequests(data.sent_requests || []);
            })
            .catch(() => {
                showStatus('Friend invites could not be loaded.', true);
            });
    }

    function refreshFriendsData() {
        loadRequests();
        const feedRequest = (feed || currentFriendsList || count) ? loadFeed() : Promise.resolve();
        return feedRequest
            .then(() => loadStatus())
            .then(() => {
                if (managerPage && currentStatus.newFriends > 0) {
                    return markFriendsSeen('friends');
                }

                return null;
            });
    }

    function runFriendSearch(query, { showShortQueryMessage = false } = {}) {
        const requestId = ++friendSearchRequestId;

        if (query.length < 2) {
            if (results) {
                results.innerHTML = '';
                results.hidden = true;
            }
            if (sentRequestsList) {
                sentRequestsList.hidden = !sentRequestsList.querySelector('[data-sent-request-id]');
            }
            if (showShortQueryMessage) {
                showStatus('Search by at least two characters.', true);
            }
            return Promise.resolve();
        }

        return fetch(`${userEndpoint('friend_search.php')}?q=${encodeURIComponent(query)}`, { credentials: 'same-origin' })
                .then((response) => response.json())
                .then((data) => {
                    if (requestId !== friendSearchRequestId) {
                        return;
                    }

                    if (!data.ok) {
                        showStatus(data.message || 'Search failed.', true);
                        return;
                    }

                    renderSearchResults(data.users || []);
                    if (sentRequestsList) {
                        sentRequestsList.hidden = true;
                    }
                })
                .catch(() => {
                    if (requestId === friendSearchRequestId) {
                        showStatus('Search failed. Please try again.', true);
                    }
                });
    }

    if (form && input) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            hideStatus();
            window.clearTimeout(friendSearchTimer);
            runFriendSearch(input.value.trim(), { showShortQueryMessage: true });
        });

        input.addEventListener('input', () => {
            hideStatus();
            window.clearTimeout(friendSearchTimer);

            const query = input.value.trim();
            friendSearchTimer = window.setTimeout(() => {
                runFriendSearch(query);
            }, 250);
        });
    }

    if (currentFriendsFilter) {
        currentFriendsFilter.addEventListener('input', () => renderCurrentFriends(currentFriendsCache));
    }

    suggestedFriendButtons.forEach((button) => {
        button.addEventListener('click', () => {
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = 'Sending...';

            postForm(userEndpoint('friend_add.php'), {
                csrf_token: csrfToken,
                friend_id: button.dataset.friendId
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Friend invite could not be sent.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = 'Invite';
                        return;
                    }

                    showStatus(data.message || 'Friend invite sent.', false);
                    if (data.xp_reward && window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data.xp_reward);
                    }
                    const list = button.closest('[data-suggested-friends-list]');
                    button.closest('.friend-suggestion-card')?.remove();
                    if (list && !list.querySelector('.friend-suggestion-card')) {
                        const empty = list.querySelector('[data-suggested-friends-empty]');
                        if (empty) {
                            empty.hidden = false;
                        }
                    }
                    refreshFriendsData();
                })
                .catch(() => {
                    showStatus('Friend invite could not be sent. Please try again.', true);
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.textContent = 'Invite';
                });
        });
    });

    profileFriendButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.profileFriendAction;
            const originalText = button.textContent;
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = action === 'accept' ? 'Accepting...' : 'Sending...';

            const request = action === 'accept'
                ? postForm(userEndpoint('friend_respond.php'), {
                    csrf_token: csrfToken,
                    request_id: button.dataset.requestId,
                    response: 'accepted'
                })
                : postForm(userEndpoint('friend_add.php'), {
                    csrf_token: csrfToken,
                    friend_id: button.dataset.friendId
                });

            request
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Friend invite could not be updated.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        button.textContent = originalText;
                        return;
                    }

                    showStatus(data.message || 'Friend invite updated.', false);
                    if (data.xp_reward && window.craftcrawlShowXpReward) {
                        window.craftcrawlShowXpReward(data.xp_reward);
                    }

                    button.classList.remove('is-loading');
                    if (action === 'accept' || data.status === 'friends') {
                        const link = document.createElement('a');
                        link.href = `profile.php?id=${encodeURIComponent(button.dataset.friendId)}`;
                        link.textContent = 'View Profile';
                        button.replaceWith(link);
                    } else {
                        button.textContent = 'Request Pending';
                        button.removeAttribute('data-profile-friend-action');
                        button.disabled = true;
                    }
                    loadStatus();
                })
                .catch(() => {
                    showStatus('Friend invite could not be updated. Please try again.', true);
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.textContent = originalText;
                });
        });
    });

    recommendationButtons.forEach((button) => {
        button.addEventListener('click', () => {
            button.disabled = true;
            button.classList.add('is-loading');
            postForm(userEndpoint('recommendation_update.php'), {
                csrf_token: csrfToken,
                recommendation_id: button.dataset.recommendationId,
                status: button.dataset.recommendationStatus
            })
                .then((data) => {
                    if (!data.ok) {
                        showStatus(data.message || 'Recommendation could not be updated.', true);
                        button.disabled = false;
                        button.classList.remove('is-loading');
                        return;
                    }

                    button.closest('.friend-recommendation-card')?.remove();
                    loadStatus();
                })
                .catch(() => {
                    showStatus('Recommendation could not be updated.', true);
                    button.disabled = false;
                    button.classList.remove('is-loading');
                });
        });
    });

    if (managerPage) {
        refreshFriendsData();
    } else if (panel) {
        ensureFeedComposer();
        loadRequests();
        loadFeed();
    } else {
        loadStatus();
    }
};
window.CraftCrawlInitFriends();
