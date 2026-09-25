let currentGameAnswer = '',
    currentGameAnswers = [],
    currentGameDisplayOptions = [],
    currentMovieAnswer = '',
    currentGameHint = '';
let gameWrongAttempts = 0,
    gameSolved = false,
    gameScore = 0,
    gameCorrectGuesses = 0,
    gameTotalAttempts = 0;
let movieSolved = false,
    movieScore = 0,
    movieCorrectGuesses = 0,
    movieTotalAttempts = 0;
let currentMovieAnswers = [],
    currentMovieDisplayOptions = [];
let currentLocationAnswer = '',
    currentLocationAnswers = [],
    currentLocationExtraHint = '',
    locationAttempts = 0,
    locationSolved = false,
    locationScore = 0,
    locationCorrectGuesses = 0,
    locationTotalAttempts = 0;
let currentLocationDisplayOptions = [];
let currentPvpCode = '',
    pvpPollTimer = null,
    pvpSuggestTimer = null,
    pvpSuggestRequest = null,
    pvpChatLastId = 0,
    pvpRematchTimer = null,
    pvpStartZeroTicks = 0;
let singleSession = null,
    singleSettingsMode = 'game',
    boundSingleSettings = false;
const SINGLE_DIFFICULTIES = {
    easy: {
        label: 'Einfach',
        points: 6,
        miss: 2
    },
    normal: {
        label: 'Normal',
        points: 10,
        miss: 5
    },
    hard: {
        label: 'Schwer',
        points: 15,
        miss: 8
    }
};

function getUrlParameter(name) {
    return new URLSearchParams(window.location.search).get(name) || '';
}

function getCurrentPage() {
    const p = getUrlParameter('page') || 'home';
    return ['home', 'game', 'pvp', 'movie', 'locations'].includes(p) ? p : 'home';
}

function showAuthTab(tab) {
    const t = tab === 'register' ? 'register' : 'login';
    $('[data-auth-tab]').each(function() {
        $(this).toggleClass('is-active', $(this).data('auth-tab') === t);
    });
    $('#loginForm').toggleClass('is-active', t === 'login');
    $('#registerForm').toggleClass('is-active', t === 'register');
}

function showAuthMessage() {
    const e = getUrlParameter('error'),
        s = getUrlParameter('success'),
        m = e || s;
    if (!m) return;
    $('#authAlert').removeClass('hidden alert-success alert-error').addClass(e ? 'alert-error' : 'alert-success').text(m);
}

function showLogin() {
    showAuthTab(getUrlParameter('tab'));
    showAuthMessage();
    $('#appShell').addClass('hidden');
    $('#authShell').removeClass('hidden');
}

function showCurrentPage() {
    const p = getCurrentPage();
    window.personAPage = p;
    $('.route-page').each(function() {
        $(this).toggleClass('hidden', $(this).data('page') !== p);
    });
}

function showApp() {
    $('#authShell').addClass('hidden');
    $('#appShell').removeClass('hidden');
    showCurrentPage();
}

function normalizeText(v) {
    return String(v || '').trim().toLowerCase();
}

function normalizeAnswerText(v) {
    return normalizeText(v).replace(/&/g, ' and ').replace(/['\u2019]/g, '').replace(/[^a-z0-9]+/g, ' ').replace(/\s+/g, ' ').trim();
}

function renderResult(t, ok, m) {
    $(t).removeClass('success error').addClass(ok ? 'success' : 'error').text(m);
}

function setNeutralResult(t, m) {
    $(t).removeClass('success error').text(m);
}

function singleDifficultyRule(d) {
    return SINGLE_DIFFICULTIES[d] || SINGLE_DIFFICULTIES.normal;
}

function singleModeLabel(mode) {
    return mode === 'movie' ? 'Guess the Movie' : mode === 'locations' ? 'Guess the Location' : 'Guess the Game';
}

function singleModeConfig(mode) {
    return mode === 'movie' ? {
        result: '#movieResult',
        guess: '#movieGuess',
        hint: '#movieHint',
        next: '#nextMovieBtn',
        check: '#checkMovieBtn',
        save: '#saveMovieScoreBtn'
    } : mode === 'locations' ? {
        result: '#locationResult',
        guess: '#locationGuess',
        hint: '#locationHint',
        next: '#nextLocationBtn',
        check: '#checkLocationBtn',
        save: '#saveLocationScoreBtn'
    } : {
        result: '#gameResult',
        guess: '#gameGuess',
        hint: '#gameHint',
        next: '#nextGameBtn',
        check: '#checkGameBtn',
        save: '#saveGameScoreBtn'
    };
}

function clampSingleRounds(v) {
    const n = parseInt(v, 10);
    return Number.isFinite(n) ? Math.max(5, Math.min(10, n)) : 5;
}

function activeSingleSession(mode) {
    return singleSession && singleSession.mode === mode && singleSession.active;
}

function singleRoundLabel(mode) {
    if (!singleSession || singleSession.mode !== mode) return null;
    const round = singleSession.active ? Math.min(singleSession.rounds, Math.max(1, singleSession.completedRounds + 1)) : singleSession.rounds;
    return round + '/' + singleSession.rounds;
}

function singleEasyHint(mode, answer, data) {
    const clean = String(answer || '').trim(),
        prefix = clean.slice(0, Math.min(clean.length, mode === 'locations' ? 3 : 4)),
        words = clean.split(/\s+/).filter(Boolean).length,
        wordText = words === 1 ? '1 Wort' : words + ' Woerter';
    if (mode === 'locations') {
        const country = (data && data.country) || String((data && data.hint) || '').replace(/^Country:\s*/, '');
        return 'Einfach: Beginnt mit "' + prefix + '"' + (country ? ' und liegt in ' + country : '') + '.';
    }
    return 'Einfach: Beginnt mit "' + prefix + '" und hat ' + wordText + '.';
}

function singleHintFor(mode, data, baseHint) {
    const difficulty = singleSession?.difficulty || 'normal',
        answer = mode === 'movie' ? (data?.title || currentMovieAnswer) : mode === 'locations' ? (data?.title || currentLocationAnswer) : (data?.title || currentGameAnswer);
    if (difficulty === 'hard') return 'Schwer: keine Hinweise in dieser Runde.';
    if (difficulty === 'easy') return singleEasyHint(mode, answer, data || {});
    return baseHint || 'Normal: ein Hinweis ist nicht verfuegbar.';
}

function updateSingleSettingsHelp() {
    const rounds = clampSingleRounds($('#singleRoundSetting').val()),
        difficulty = $('#singleDifficultySetting').val() || 'normal',
        rule = singleDifficultyRule(difficulty),
        hint = difficulty === 'hard' ? 'keine Hinweise' : difficulty === 'easy' ? 'sehr starker Hinweis' : 'ein normaler Hinweis';
    $('#singleSettingsHelp').text(rounds + ' Runden | ' + rule.points + ' Punkte pro Treffer | ' + hint + '.');
}

function openSingleSettings(mode) {
    singleSettingsMode = ['game', 'movie', 'locations'].includes(mode) ? mode : 'game';
    const existing = singleSession && singleSession.mode === singleSettingsMode ? singleSession : null;
    $('#singleSettingsTitle').text(singleModeLabel(singleSettingsMode) + ' einstellen');
    $('#singleRoundSetting').val(existing ? existing.rounds : 5);
    $('#singleDifficultySetting').val(existing ? existing.difficulty : 'normal');
    updateSingleSettingsHelp();
    $('#singleSettingsModal').removeClass('hidden').attr('aria-hidden', 'false');
    setTimeout(() => $('#singleRoundSetting').trigger('focus'), 40);
}

function closeSingleSettings() {
    $('#singleSettingsModal').addClass('hidden').attr('aria-hidden', 'true');
}

function cancelSingleSettings() {
    closeSingleSettings();
    singleSession = null;
    if (appReady) navigateToPage('home', true);
    else window.location.href = 'index.html?page=home';
}

function bindSingleSettingsOnce() {
    if (boundSingleSettings) return;
    boundSingleSettings = true;
    $('#singleRoundSetting,#singleDifficultySetting').on('input change', updateSingleSettingsHelp);
    $('#startSingleModeBtn').on('click', () => startSingleSession(singleSettingsMode, $('#singleRoundSetting').val(), $('#singleDifficultySetting').val()));
    $('#cancelSingleModeBtn').on('click', cancelSingleSettings);
    $('#singleSettingsModal').on('click', function(e) {
        if (e.target === this) cancelSingleSettings();
    });
    $(document).on('keydown', e => {
        if (e.key === 'Escape' && !$('#singleSettingsModal').hasClass('hidden')) cancelSingleSettings();
    });
}

function hideSingleEndModal() {
    $('#singleEndModal').addClass('hidden').attr('aria-hidden', 'true').removeData('mode');
    $('#singleEndResult').empty();
}

function getRankNextTarget(rank) {
    const value = String(rank || '');
    if (value.startsWith('Top 100')) return null;
    const p = value.split(' '),
        tier = p[0],
        div = p[1] || 'V',
        bounds = {
            Bronze: [0, 500],
            Silver: [500, 1200],
            Gold: [1200, 2000],
            Platinum: [2000, 3000],
            Champion: [3000, 5000]
        },
        idx = ['V', 'IV', 'III', 'II', 'I'].indexOf(div),
        b = bounds[tier] || [0, 500],
        step = Math.ceil((b[1] - b[0]) / 5);
    return idx >= 4 ? b[1] : b[0] + (Math.max(0, idx) + 1) * step;
}

function renderSingleEndModal(data, summary, error) {
    const box = $('#singleEndResult').empty(),
        after = data?.after || {},
        before = data?.before || {},
        delta = Number(data?.delta) || 0,
        rank = after.rank || before.rank || 'Bronze V',
        points = Number(after.points ?? before.points ?? 0),
        floor = getRankFloor(rank),
        target = getRankNextTarget(rank),
        promoted = before.rank && after.rank && before.rank !== after.rank,
        startProgress = target ? Math.max(0, Math.min(100, Math.round((((Number(before.points) || 0) - floor) / (target - floor)) * 100))) : 100,
        progress = target ? Math.max(0, Math.min(100, Math.round(((points - floor) / (target - floor)) * 100))) : 100;
    box.removeClass('is-rankup is-loss').toggleClass('is-rankup', promoted).toggleClass('is-loss', Boolean(error));
    box.append($('<div>').addClass('rank-popup-title').text(error ? 'Nicht gespeichert' : 'Single Run gespeichert')).append($('<div>').addClass('rank-result-head').append(rankIcon(rank, 'rank-icon-large')).append($('<div>').append($('<h2>').attr('id', 'singleEndTitle').text(error ? 'Score konnte nicht gespeichert werden' : (promoted ? 'Rank up!' : rank))).append($('<p>').addClass('muted').text(singleModeLabel(summary.mode) + ' | ' + singleDifficultyRule(summary.difficulty).label + ' | ' + summary.correct + '/' + summary.rounds + ' richtig'))));
    box.append($('<div>').addClass('rank-line').append($('<span>').addClass('rank-name').append(rankIcon(rank, 'rank-icon-inline')).append($('<span>').text(rank))).append($('<span>').addClass('rank-delta').toggleClass('is-loss', delta < 0 || Boolean(error)).text(error ? 'Fehler' : ((delta >= 0 ? '+' : '') + delta + ' Punkte'))));
    const fill = $('<span>').css('width', startProgress + '%');
    box.append($('<div>').addClass('rank-progress').toggleClass('is-loss', delta < 0 || Boolean(error)).append(fill));
    box.append($('<p>').addClass('muted').text(error ? error : (target ? points + '/' + target + ' Punkte bis zum naechsten Rank' : points + ' Punkte | Top 100')));
    box.append($('<p>').addClass('muted').text('Run Score: ' + (summary.score || 0) + ' Punkte. Speichern passiert automatisch.'));
    box.append($('<div>').addClass('button-row post-match-actions').append($('<button>').attr('type', 'button').addClass('primary-btn').text('Next round').on('click', () => chooseSingleEnd('next'))).append($('<button>').attr('type', 'button').addClass('ghost-btn').text('Back to lobby').on('click', () => chooseSingleEnd('home'))));
    $('#singleEndModal').data('mode', summary.mode).removeClass('hidden').attr('aria-hidden', 'false');
    requestAnimationFrame(() => requestAnimationFrame(() => fill.css('width', progress + '%')));
}

function submitSingleRun(mode, prefix) {
    const summary = singleModeSummary(mode),
        cfg = singleModeConfig(mode);
    if (!summary.complete) return;
    if (singleSession) {
        if (singleSession.saving) return;
        singleSession.saving = true;
    }
    setNeutralResult(cfg.result, (prefix || '') + 'Ranking wird automatisch gespeichert...');
    apiRequest('single-submit', 'POST', {
        mode: summary.mode,
        rounds: summary.rounds,
        correct: summary.correct,
        skipped: summary.skipped,
        difficulty: summary.difficulty
    }).done(d => {
        if (singleSession && singleSession.mode === mode) {
            singleSession.saved = true;
            singleSession.savedData = d;
        }
        const delta = Number(d.delta) || 0;
        renderResult(cfg.result, true, 'Ranking gespeichert: ' + (delta >= 0 ? '+' : '') + delta + ' Punkte.');
        loadLeaderboards();
        renderSingleEndModal(d, summary);
    }).fail(x => {
        const msg = x.responseJSON?.error || 'Score konnte nicht gespeichert werden.';
        renderResult(cfg.result, false, msg);
        renderSingleEndModal(null, summary, msg);
    }).always(() => {
        if (singleSession) singleSession.saving = false;
    });
}

function chooseSingleEnd(choice) {
    const mode = $('#singleEndModal').data('mode') || singleSettingsMode || 'game';
    hideSingleEndModal();
    singleSession = null;
    resetSingleCounters(mode);
    if (choice === 'next') {
        singleSettingsMode = mode;
        if (getCurrentPage() !== mode) navigateToPage(mode, true);
        else openSingleSettings(mode);
        return;
    }
    navigateToPage('home', true);
}

function resetSingleCounters(mode) {
    if (mode === 'game') {
        gameWrongAttempts = 0;
        gameSolved = false;
        gameScore = 0;
        gameCorrectGuesses = 0;
        gameTotalAttempts = 0;
        currentGameAnswer = '';
        currentGameAnswers = [];
        currentGameDisplayOptions = [];
        $('#gameOptions').addClass('hidden').empty();
    }
    if (mode === 'movie') {
        movieSolved = false;
        movieScore = 0;
        movieCorrectGuesses = 0;
        movieTotalAttempts = 0;
        currentMovieAnswer = '';
        currentMovieAnswers = [];
        currentMovieDisplayOptions = [];
        $('#movieOptions').addClass('hidden').empty();
    }
    if (mode === 'locations') {
        locationAttempts = 0;
        locationSolved = false;
        locationScore = 0;
        locationCorrectGuesses = 0;
        locationTotalAttempts = 0;
        currentLocationAnswer = '';
        currentLocationAnswers = [];
        currentLocationDisplayOptions = [];
        currentLocationExtraHint = '';
        $('#locationOptions').addClass('hidden').empty();
        $('#requestLocationHintBtn').addClass('hidden');
    }
    $(singleModeConfig(mode).save).addClass('hidden').prop('disabled', true);
    updateGameRanking();
    updateMovieRanking();
    updateLocationRanking();
}

function syncSingleCounters(mode) {
    const s = singleSession;
    if (!s || s.mode !== mode) return;
    if (mode === 'game') {
        gameScore = s.score;
        gameCorrectGuesses = s.correct;
        gameTotalAttempts = s.totalAttempts;
    }
    if (mode === 'movie') {
        movieScore = s.score;
        movieCorrectGuesses = s.correct;
        movieTotalAttempts = s.totalAttempts;
    }
    if (mode === 'locations') {
        locationScore = s.score;
        locationCorrectGuesses = s.correct;
        locationTotalAttempts = s.totalAttempts;
    }
}

function updateSingleRanking(mode) {
    if (mode === 'game') updateGameRanking();
    if (mode === 'movie') updateMovieRanking();
    if (mode === 'locations') updateLocationRanking();
}

function prepareSingleRound(mode) {
    const cfg = singleModeConfig(mode),
        s = singleSession;
    if (s) {
        s.currentRound = Math.min(s.rounds, Math.max(1, s.completedRounds + 1));
        s.currentSolved = false;
    }
    $(cfg.result).removeClass('success error').text('');
    $(cfg.guess).val('').prop('disabled', false);
    $(cfg.next).removeClass('hidden').prop('disabled', false).text('Skip round');
    $(cfg.check).removeClass('hidden').prop('disabled', false);
    $(cfg.save).addClass('hidden').prop('disabled', true);
    $('#gameOptions,#movieOptions,#locationOptions').addClass('hidden').empty();
    if (mode === 'locations') $('#requestLocationHintBtn').addClass('hidden');
    if (s) $(cfg.hint).text(singleDifficultyRule(s.difficulty).label + ' | Runde ' + s.currentRound + '/' + s.rounds + ' wird geladen...');
    updateSingleRanking(mode);
}

function ensureSingleSession(mode) {
    if (activeSingleSession(mode)) return true;
    openSingleSettings(mode);
    return false;
}

function startSingleSession(mode, rounds, difficulty) {
    mode = ['game', 'movie', 'locations'].includes(mode) ? mode : 'game';
    difficulty = SINGLE_DIFFICULTIES[difficulty] ? difficulty : 'normal';
    hideSingleEndModal();
    singleSession = {
        mode,
        rounds: clampSingleRounds(rounds),
        difficulty,
        currentRound: 1,
        completedRounds: 0,
        correct: 0,
        skipped: 0,
        score: 0,
        totalAttempts: 0,
        currentSolved: false,
        active: true,
        saved: false,
        saving: false,
        finished: false
    };
    resetSingleCounters(mode);
    closeSingleSettings();
    setNeutralResult(singleModeConfig(mode).result, 'Runde 1/' + singleSession.rounds + ' startet. Schwierigkeit: ' + singleDifficultyRule(difficulty).label + '.');
    if (mode === 'movie') loadRandomMovie();
    else if (mode === 'locations') loadRandomLocation();
    else loadRandomGame();
}

function completeSingleRound(mode, correct) {
    const s = singleSession;
    if (!s || s.mode !== mode || s.currentSolved || s.completedRounds >= s.rounds) return false;
    const rule = singleDifficultyRule(s.difficulty);
    s.currentSolved = true;
    s.completedRounds++;
    if (correct) {
        s.correct++;
        s.score += rule.points;
    } else {
        s.skipped++;
        s.score = Math.max(0, s.score - rule.miss);
    }
    syncSingleCounters(mode);
    updateSingleRanking(mode);
    return true;
}

function finishSingleSession(mode, prefix) {
    const s = singleSession,
        cfg = singleModeConfig(mode);
    if (!s || s.mode !== mode || s.finished) return;
    s.finished = true;
    s.active = false;
    s.currentRound = s.rounds;
    syncSingleCounters(mode);
    updateSingleRanking(mode);
    $(cfg.check).addClass('hidden');
    $(cfg.next).addClass('hidden');
    $(cfg.save).addClass('hidden').prop('disabled', true);
    $(cfg.guess).prop('disabled', true);
    if (mode === 'locations') $('#requestLocationHintBtn').addClass('hidden');
    submitSingleRun(mode, prefix || '');
}

function advanceSingleRound(mode) {
    const s = singleSession,
        cfg = singleModeConfig(mode);
    if (!activeSingleSession(mode)) {
        openSingleSettings(mode);
        return;
    }
    $(cfg.next).prop('disabled', true).text('Loading...');
    if (!s.currentSolved) completeSingleRound(mode, false);
    if (s.completedRounds >= s.rounds) {
        finishSingleSession(mode);
        return;
    }
    s.currentRound = Math.min(s.rounds, s.completedRounds + 1);
    updateSingleRanking(mode);
    if (mode === 'movie') loadRandomMovie();
    else if (mode === 'locations') loadRandomLocation();
    else loadRandomGame();
}

function registerSingleAttempt(mode) {
    const s = singleSession;
    if (s && s.mode === mode) {
        s.totalAttempts++;
        syncSingleCounters(mode);
        updateSingleRanking(mode);
        return;
    }
    if (mode === 'game') {
        gameTotalAttempts++;
        updateGameRanking();
    }
    if (mode === 'movie') {
        movieTotalAttempts++;
        updateMovieRanking();
    }
    if (mode === 'locations') {
        locationTotalAttempts++;
        updateLocationRanking();
    }
}

function handleSingleCorrect(mode) {
    const s = singleSession;
    if (!s || s.mode !== mode) return false;
    const points = singleDifficultyRule(s.difficulty).points;
    completeSingleRound(mode, true);
    const cfg = singleModeConfig(mode);
    $(cfg.check).addClass('hidden');
    if (s.completedRounds >= s.rounds) {
        finishSingleSession(mode, 'Richtig! +' + points + ' Punkte. ');
        return true;
    }
    s.currentRound = Math.min(s.rounds, s.completedRounds + 1);
    updateSingleRanking(mode);
    $(cfg.next).removeClass('hidden').prop('disabled', true).text('Loading...');
    renderResult(cfg.result, true, 'Richtig! +' + points + ' Punkte. Naechste Runde startet...');
    setTimeout(() => advanceSingleRound(mode), 650);
    return true;
}

function singleModeSummary(mode) {
    const cfg = singleModeConfig(mode);
    if (singleSession && singleSession.mode === mode) {
        return {
            mode,
            rounds: singleSession.rounds,
            correct: singleSession.correct,
            skipped: singleSession.skipped,
            attempts: singleSession.totalAttempts,
            target: cfg.result,
            difficulty: singleSession.difficulty,
            complete: !singleSession.active && singleSession.completedRounds >= singleSession.rounds,
            saved: singleSession.saved,
            score: singleSession.score
        };
    }
    const data = mode === 'movie' ? {
        correct: movieCorrectGuesses,
        attempts: movieTotalAttempts,
        target: '#movieResult'
    } : mode === 'locations' ? {
        correct: locationCorrectGuesses,
        attempts: locationTotalAttempts,
        target: '#locationResult'
    } : {
        correct: gameCorrectGuesses,
        attempts: gameTotalAttempts,
        target: '#gameResult'
    };
    const rounds = Math.max(5, Math.min(10, Math.max(data.correct, data.attempts, 5))),
        correct = Math.max(0, Math.min(rounds, data.correct));
    return {
        mode,
        rounds,
        correct,
        skipped: Math.max(0, rounds - correct),
        attempts: data.attempts,
        target: data.target,
        difficulty: 'normal',
        complete: data.attempts > 0,
        saved: false,
        score: correct * 10
    };
}

function saveScore(mode, score) {
    const summary = singleModeSummary(mode);
    if (!summary.complete) {
        renderResult(summary.target, false, 'Beende zuerst alle Runden, bevor du speicherst.');
        return;
    }
    if (summary.saved) {
        renderResult(summary.target, true, 'Dieser Run ist schon gespeichert. Starte eine neue Session fuer einen neuen Score.');
        return;
    }
    apiRequest('single-submit', 'POST', {
        mode: summary.mode,
        rounds: summary.rounds,
        correct: summary.correct,
        skipped: summary.skipped,
        difficulty: summary.difficulty
    }).done(d => {
        const delta = Number(d.delta) || 0;
        if (singleSession && singleSession.mode === mode) singleSession.saved = true;
        renderResult(summary.target, true, 'Ranking gespeichert: ' + (delta >= 0 ? '+' : '') + delta + ' Punkte (' + singleDifficultyRule(summary.difficulty).label + ', ' + summary.correct + '/' + summary.rounds + ').');
        loadLeaderboards();
    }).fail(x => renderResult(summary.target, false, x.responseJSON?.error || 'Score konnte nicht gespeichert werden.'));
}

function getAnswerDisplayOptions(title, extras) {
    const clean = String(title || '').trim(),
        map = new Map(),
        short = clean.split(/:|\s-\s|\|/)[0].trim();

    function add(x) {
        const value = String(x || '').trim(),
            n = normalizeAnswerText(value);
        if (n && !map.has(n)) map.set(n, value);
    }
    add(clean);
    add(short);
    (Array.isArray(extras) ? extras : []).forEach(add);
    [clean, short].map(normalizeAnswerText).forEach(x => {
        ['remastered', 'remaster', 'standard', 'deluxe', 'ultimate', 'complete', 'definitive', 'anniversary', 'edition', 'version', 'game', 'movie', 'film', 'of', 'the', 'year', 'goty'].forEach(w => x = normalizeAnswerText(x.replace(new RegExp('(^| )' + w + '( |$)', 'g'), ' ')));
        add(x);
    });
    if (normalizeAnswerText(clean).includes('spider man')) {
        add('Spider Man');
        add('Spiderman');
    }
    return Array.from(map.values()).filter(Boolean);
}

function getGameDisplayOptions(title) {
    return getAnswerDisplayOptions(title);
}

function answerDistance(a, b) {
    a = normalizeAnswerText(a);
    b = normalizeAnswerText(b);
    if (a === b) return 0;
    if (!a) return b.length;
    if (!b) return a.length;
    let prev = Array.from({
        length: b.length + 1
    }, (_, i) => i);
    for (let i = 1; i <= a.length; i++) {
        let row = [i];
        for (let j = 1; j <= b.length; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            row[j] = Math.min(row[j - 1] + 1, prev[j] + 1, prev[j - 1] + cost);
        }
        prev = row;
    }
    return prev[b.length];
}

function shouldSuggestAnswer(option, guess) {
    const o = normalizeAnswerText(option),
        g = normalizeAnswerText(guess);
    if (!o || !g || g.length < 2) return false;
    if (o.includes(g) || g.includes(o)) return true;
    if (o.split(' ').some(w => w.startsWith(g) || (g.length >= 3 && answerDistance(w, g) <= 1))) return true;
    if (g.length < 3) return false;
    const limit = Math.max(1, Math.floor(Math.max(o.length, g.length) * 0.22));
    return answerDistance(o, g) <= limit;
}

function renderAnswerOptions(inputSelector, boxSelector, options, solved) {
    const guess = $(inputSelector).val(),
        box = $(boxSelector);
    if (!guess || normalizeAnswerText(guess).length < 2 || solved) {
        box.addClass('hidden').empty();
        return;
    }
    const matches = (options || []).filter(o => shouldSuggestAnswer(o, guess)).slice(0, 5);
    box.empty();
    if (!matches.length) {
        box.addClass('hidden');
        return;
    }
    matches.forEach(o => $('<button>').attr('type', 'button').addClass('option-chip').text(o).on('click', () => {
        $(inputSelector).val(o).trigger('focus');
        box.addClass('hidden').empty();
    }).appendTo(box));
    box.removeClass('hidden');
}

function isAnswerCorrect(g, answers) {
    const n = normalizeAnswerText(g);
    return (answers || []).some(a => normalizeAnswerText(a) === n);
}

function isGameAnswerCorrect(g) {
    return isAnswerCorrect(g, currentGameAnswers);
}

function isMovieAnswerCorrect(g) {
    return isAnswerCorrect(g, currentMovieAnswers);
}

function renderGameOptions() {
    renderAnswerOptions('#gameGuess', '#gameOptions', currentGameDisplayOptions, gameSolved);
}

function renderMovieOptions() {
    renderAnswerOptions('#movieGuess', '#movieOptions', currentMovieDisplayOptions, movieSolved);
}

function renderLocationOptions() {
    renderAnswerOptions('#locationGuess', '#locationOptions', currentLocationDisplayOptions, locationSolved);
}

function updateGameRanking() {
    $('#gameScoreValue').text(gameScore);
    $('#gameCorrectValue').text(gameCorrectGuesses);
    $('#gameAttemptsValue').text(singleRoundLabel('game') || gameTotalAttempts);
}

function updateMovieRanking() {
    $('#movieScoreValue').text(movieScore);
    $('#movieCorrectValue').text(movieCorrectGuesses);
    $('#movieAttemptsValue').text(singleRoundLabel('movie') || movieTotalAttempts);
}

function updateLocationRanking() {
    $('#locationScoreValue').text(locationScore);
    $('#locationCorrectValue').text(locationCorrectGuesses);
    $('#locationAttemptsValue').text(singleRoundLabel('locations') || locationTotalAttempts);
}

function isLocationAnswerCorrect(g) {
    return isAnswerCorrect(g, currentLocationAnswers);
}

function pvpRequest(route, method, data) {
    return $.ajax({
        url: '../backend/api/index.php?route=' + route,
        type: method,
        dataType: 'json',
        contentType: 'application/json',
        data: data ? JSON.stringify(data) : undefined
    });
}

function pvpStateRequest(code) {
    return $.ajax({
        url: '../backend/api/index.php?route=pvp-state&code=' + encodeURIComponent(code) + '&_=' + Date.now(),
        type: 'GET',
        dataType: 'json',
        cache: false
    });
}

function pvpSuggestState(code, guess) {
    return pvpRequest('pvp-suggest', 'POST', {
        code,
        guess
    });
}

function pvpChatListRequest(code) {
    return $.ajax({
        url: '../backend/api/index.php?route=pvp-chat-list&code=' + encodeURIComponent(code) + '&after_id=' + encodeURIComponent(pvpChatLastId),
        type: 'GET',
        dataType: 'json'
    });
}

function getPvpModeLabel(m) {
    return m === 'movie' ? 'Guess the Movie' : m === 'locations' ? 'Guess the Location' : 'Guess the Game';
}

function getPvpPlaceholder(m) {
    return m === 'locations' ? 'Enter city name' : m === 'movie' ? 'Enter movie title' : 'Enter game title';
}

function getDateTimeMs(v) {
    if (!v) return 0;
    const t = new Date(String(v).replace(' ', 'T')).getTime();
    return Number.isNaN(t) ? 0 : t;
}

function getDateTimeLeft(v, serverNow) {
    const t = getDateTimeMs(v),
        now = getDateTimeMs(serverNow) || Date.now();
    return t ? Math.max(0, Math.ceil((t - now) / 1000)) : 0;
}

function getPvpTimeLeft(r, serverNow) {
    return !r || !r.ends_at || r.completed ? 0 : getDateTimeLeft(r.ends_at, serverNow);
}

function formatPvpDuration(s) {
    s = Number(s) || 0;
    if (s >= 60 && s % 60 === 0) {
        const m = s / 60;
        return m + (m === 1 ? ' minute' : ' minutes');
    }
    return s > 60 ? Math.floor(s / 60) + ' min ' + (s % 60) + ' sec' : s + ' seconds';
}

function getRankFloor(r) {
    if (!r) return 0;
    if (String(r).startsWith('Top 100')) return 5000;
    const p = String(r).split(' '),
        tier = p[0],
        div = p[1] || 'V',
        bounds = {
            Bronze: [0, 500],
            Silver: [500, 1200],
            Gold: [1200, 2000],
            Platinum: [2000, 3000],
            Champion: [3000, 5000]
        },
        idx = ['V', 'IV', 'III', 'II', 'I'].indexOf(div),
        b = bounds[tier] || [0, 500],
        step = Math.ceil((b[1] - b[0]) / 5);
    return b[0] + Math.max(0, idx) * step;
}

function getRankIconName(rank) {
    const value = String(rank || 'Bronze');
    const top = value.match(/^Top 100 #(\d+)/);
    if (top) {
        const pos = Math.max(1, Math.min(100, parseInt(top[1], 10) || 100));
        return 'top' + pos;
    }
    const t = value.split(' ')[0].toLowerCase();
    return t === 'top' ? 'top100' : (t || 'bronze');
}

function getRankDivision(rank) {
    const p = String(rank || '').split(' ');
    return ['V', 'IV', 'III', 'II', 'I'].includes(p[1] || '') ? p[1] : '';
}

function rankIcon(rank, size) {
    const value = rank || 'Rank',
        division = getRankDivision(value),
        tier = getRankIconName(value);
    const icon = $('<span>').addClass('rank-icon ' + (size || 'rank-icon-small') + ' rank-tier-' + tier).attr('aria-label', value).attr('title', value).append($('<img>').attr('src', './assets/ranks/' + tier + '.svg').attr('alt', ''));
    if (division) icon.append($('<span>').addClass('rank-division').text(division));
    return icon;
}

function getPvpSettings() {
    const r = parseInt($('#pvpRoundCount').val(), 10),
        d = parseInt($('#pvpRoundTime').val(), 10);
    return {
        mode: $('#pvpMode').val() || 'game',
        max_rounds: Number.isFinite(r) ? Math.max(1, Math.min(20, r)) : 5,
        round_duration: Number.isFinite(d) ? Math.max(15, Math.min(180, d)) : 60
    };
}

function createPvpRoom() {
    setNeutralResult('#pvpLobbyResult', 'Creating room...');
    $('#confirmPvpRoomBtn').prop('disabled', true);
    pvpRequest('pvp-create', 'POST', getPvpSettings()).done(d => {
        $('#pvpRoomCode').val(d.match.code);
        $('#pvpSettingsPanel').addClass('hidden');
        pvpChatLastId = 0;
        $('#pvpChatMessages').empty();
        startPvpPolling(d.match.code);
    }).fail(x => renderResult('#pvpLobbyResult', false, x.responseJSON?.error || 'Room could not be created.')).always(() => $('#confirmPvpRoomBtn').prop('disabled', false));
}

function clearPvpSuggestions() {
    $('#pvpOptions').addClass('hidden').empty();
    if (pvpSuggestTimer) clearTimeout(pvpSuggestTimer);
    pvpSuggestTimer = null;
    if (pvpSuggestRequest) {
        pvpSuggestRequest.abort();
        pvpSuggestRequest = null;
    }
}

function renderPvpSuggestions(s) {
    const box = $('#pvpOptions').empty();
    if (!Array.isArray(s) || !s.length) {
        box.addClass('hidden');
        return;
    }
    s.forEach(v => $('<button>').attr('type', 'button').addClass('option-chip').text(v).on('click', () => {
        $('#pvpGuess').val(v).focus();
        clearPvpSuggestions();
    }).appendTo(box));
    box.removeClass('hidden');
}

function loadPvpSuggestions() {
    const g = $('#pvpGuess').val().trim();
    if (!currentPvpCode || g.length < 2 || $('#pvpGuess').prop('disabled')) {
        clearPvpSuggestions();
        return;
    }
    if (pvpSuggestTimer) clearTimeout(pvpSuggestTimer);
    pvpSuggestTimer = setTimeout(() => {
        if (pvpSuggestRequest) pvpSuggestRequest.abort();
        pvpSuggestRequest = pvpSuggestState(currentPvpCode, g).done(d => {
            if ($('#pvpGuess').val().trim() === g) renderPvpSuggestions(d.suggestions || []);
        }).always(() => pvpSuggestRequest = null);
    }, 220);
}

function stopPvpPolling() {
    if (pvpPollTimer) {
        clearInterval(pvpPollTimer);
        pvpPollTimer = null;
    }
}

function stopPvpRematchWait() {
    if (pvpRematchTimer) {
        clearInterval(pvpRematchTimer);
        pvpRematchTimer = null;
    }
}

function startPvpPolling(code) {
    currentPvpCode = code;
    stopPvpPolling();
    stopPvpRematchWait();
    pvpChatLastId = 0;
    $('#pvpChatMessages').empty();
    loadPvpState();
    loadPvpChat();
    pvpPollTimer = setInterval(loadPvpState, 1000);
}

function renderPvpPlayers(players) {
    const box = $("#pvpPlayers").empty();
    $("<span>").text("Scoreboard").appendTo(box);
    players.forEach(p => {
        const a = avatarForPlayer(p),
            anim = avatarAnimationValue(a);
        $("<div>").addClass("ranking-row").toggleClass("has-avatar-animation", anim !== "none").attr("data-avatar-animation", anim).append($("<span>").each(function() {
            applyAvatarElement(this, a, "avatar-tiny");
        })).append($("<strong>").text(p.name + (p.answered ? " scored" : ""))).append($("<b>").text(p.score)).appendTo(box);
    });
}

function renderPvpWaitingPlayers(players) {
    const box = $("#pvpWaitingPlayers").empty();
    $("<span>").text("Players").appendTo(box);
    players.forEach(p => {
        const a = avatarForPlayer(p),
            anim = avatarAnimationValue(a);
        $("<div>").addClass("ranking-row").toggleClass("has-avatar-animation", anim !== "none").attr("data-avatar-animation", anim).append($("<span>").each(function() {
            applyAvatarElement(this, a, "avatar-tiny");
        })).append($("<strong>").text(p.name)).append($("<b>").text(p.ready ? "Ready" : "Not ready")).appendTo(box);
    });
}

function hidePvpEndModal() {
    $('#pvpEndModal').addClass('hidden').attr('aria-hidden', 'true').removeData('rankKey');
    $('#pvpEndRankResult').empty();
}

function renderPvpRankResult(data, winner) {
    const change = data.ranking_change?.[data.match.mode],
        global = data.ranking_change?.global,
        inlineBox = $('#pvpRankResult').addClass('hidden').empty(),
        box = $('#pvpEndRankResult');
    if (!change) {
        hidePvpEndModal();
        return;
    }
    const after = change.after || {},
        before = change.before || {},
        key = [data.match.code, data.match.winner_user_id, after.rank, after.points, change.delta].join('|');
    if (!$('#pvpEndModal').hasClass('hidden') && $('#pvpEndModal').data('rankKey') === key) return;
    $('#pvpEndModal').data('rankKey', key);
    box.empty();
    const target = change.next_target,
        floor = change.rank_floor ?? getRankFloor(after.rank),
        points = Number(after.points) || 0,
        beforePoints = Number(before.points) || 0,
        startProgress = target ? Math.max(0, Math.min(100, Math.round(((beforePoints - floor) / (target - floor)) * 100))) : 100,
        progress = target ? Math.max(0, Math.min(100, Math.round(((points - floor) / (target - floor)) * 100))) : 100,
        promoted = before.rank && after.rank && before.rank !== after.rank,
        won = winner && winner.id === data.user.id,
        delta = Number(change.delta) || 0,
        title = won ? 'Gewonnen' : 'Verloren';
    inlineBox.append($('<div>').addClass('rank-popup-title').text(title));
    box.removeClass('is-rankup is-loss').toggleClass('is-rankup', promoted).toggleClass('is-loss', !won).append($('<div>').addClass('rank-popup-title').text(title)).append($('<div>').addClass('rank-result-head').append(rankIcon(after.rank, 'rank-icon-large')).append($('<div>').append($('<h2>').attr('id', 'pvpEndTitle').text(promoted ? 'Rank up!' : after.rank)).append($('<p>').addClass('muted').text(promoted ? before.rank + ' -> ' + after.rank : 'Aktueller Rank: ' + after.rank))));
    box.append($('<div>').addClass('rank-line').append($('<span>').addClass('rank-name').append(rankIcon(after.rank, 'rank-icon-inline')).append($('<span>').text(after.rank))).append($('<span>').addClass('rank-delta').toggleClass('is-loss', delta < 0).text((delta >= 0 ? '+' : '') + delta + ' Punkte')));
    const fill = $('<span>').css('width', startProgress + '%');
    box.append($('<div>').addClass('rank-progress').toggleClass('is-loss', delta < 0).append(fill));
    box.append($('<p>').addClass('muted').text(target ? points + '/' + target + ' Punkte bis zum naechsten Rank' : points + ' Punkte | Top 100'));
    box.append($('<p>').addClass('muted').text('Match Score: ' + (change.score || 0) + '/' + (change.max_score || 0)));
    if (global) box.append($('<p>').addClass('muted').text('Global: ' + global.after.rank + ' (' + global.after.points + ' Punkte, ' + (global.delta >= 0 ? '+' : '') + global.delta + ')'));
    box.append($('<div>').addClass('button-row post-match-actions').append($('<button>').attr('type', 'button').addClass('primary-btn').text('Revanche').on('click', () => choosePvpPostMatch('rematch'))).append($('<button>').attr('type', 'button').addClass('ghost-btn').text('Back to lobby').on('click', () => choosePvpPostMatch('leave'))));
    $('#pvpEndModal').removeClass('hidden').attr('aria-hidden', 'false');
    requestAnimationFrame(() => requestAnimationFrame(() => fill.css('width', progress + '%')));
}

function renderPvpState(data) {
    if (data.post_match_action === 'leave') {
        returnPvpLobby('Der andere Spieler ist zur Lobby gegangen.');
        return;
    }
    const match = data.match || {},
        round = data.round,
        players = data.players || [],
        label = getPvpModeLabel(match.mode);
    currentPvpCode = match.code || currentPvpCode;
    $('#pvpLobbyPanel').toggleClass('hidden', Boolean(currentPvpCode));
    $('#pvpWaitingPanel').toggleClass('hidden', !['waiting', 'ready', 'starting'].includes(match.status));
    $('#pvpMatchPanel').toggleClass('hidden', !['playing', 'finished'].includes(match.status));
    $('#pvpChatPanel').toggleClass('hidden', !currentPvpCode);
    if (match.status !== 'starting') pvpStartZeroTicks = 0;
    if (match.status === 'waiting') {
        $('#pvpWaitingCode').text('Code: ' + match.code);
        $('#pvpWaitingSettings').text(label + ' | ' + match.max_rounds + ' rounds | ' + formatPvpDuration(match.round_duration) + ' per image');
        renderPvpWaitingPlayers(players);
        $('#pvpReadyBtn').addClass('hidden');
        $('#pvpReadyStatus').text('Waiting for the second player.');
        return;
    }
    if (match.status === 'ready') {
        const me = players.find(p => p.id === data.user.id);
        $('#pvpWaitingCode').text('Code: ' + match.code);
        $('#pvpWaitingSettings').text(label + ' | ' + match.max_rounds + ' rounds | ' + formatPvpDuration(match.round_duration) + ' per image');
        renderPvpWaitingPlayers(players);
        $('#pvpReadyBtn').toggleClass('hidden', Boolean(me && me.ready));
        $('#pvpReadyStatus').text((me && me.ready ? 'You are ready. ' : 'Click Ready. ') + 'Ready time left: ' + getDateTimeLeft(match.ready_deadline_at, data.server_now) + 's');
        return;
    }
    if (match.status === 'starting') {
        const startLeft = getDateTimeLeft(match.starts_at, data.server_now);
        hidePvpEndModal();
        $('#pvpWaitingCode').text('Starting');
        renderPvpWaitingPlayers(players);
        $('#pvpReadyBtn').addClass('hidden');
        $('#pvpReadyStatus').text('Both players are ready. Match starts in ' + startLeft + 's');
        if (startLeft <= 0) {
            pvpStartZeroTicks++;
            setTimeout(loadPvpState, 250);
            if (pvpStartZeroTicks >= 6) window.location.href = 'index.html?page=pvp&room=' + encodeURIComponent(currentPvpCode);
        }
        return;
    }
    if (match.status === 'playing') {
        hidePvpEndModal();
        $('#pvpWaitingPanel').addClass('hidden');
        $('#pvpMatchPanel').removeClass('hidden');
        $('#pvpRankResult').addClass('hidden').empty();
        renderPvpPlayers(players);
        if (!round) {
            $('#pvpImage').attr('src', '').attr('alt', label);
            $('#pvpRoundCounter').text('Loading round');
            $('#pvpTimer').text('Starting round...');
            $('#pvpHint').text('Loading...');
            $('#pvpGuess,#submitPvpAnswerBtn').prop('disabled', true);
            setNeutralResult('#pvpResult', 'Round is loading...');
            return;
        }
        $('#pvpImage').attr('src', round.image || '').attr('alt', label);
        $('#pvpRoundCounter').text(match.sudden_death ? 'Sudden Death' : 'Round ' + round.number + '/' + match.max_rounds);
        $('#pvpHint').text(round.hint || 'No hint');
        $('#pvpTimer').text(match.sudden_death ? (round.completed ? 'Sudden Death complete' : 'Sudden Death: fastest correct answer wins') : (round.completed ? 'Round complete' : 'Time left: ' + getPvpTimeLeft(round, data.server_now) + 's'));
        $('#pvpGuess').attr('placeholder', getPvpPlaceholder(match.mode));
        $('#pvpMatchTitle').text(match.sudden_death ? 'Sudden Death' : (round.title || label));
        const my = round.my_answer,
            can = !round.completed && !my && getPvpTimeLeft(round, data.server_now) > 0;
        $('#pvpGuess').prop('disabled', !can);
        $('#submitPvpAnswerBtn').prop('disabled', !can);
        if (!can) clearPvpSuggestions();
        if (my) renderResult('#pvpResult', true, match.sudden_death ? 'Correct! You won sudden death.' : 'Correct! You scored 10 points. Next image starts soon.');
        else if (data.last_attempt && !data.last_attempt.is_correct) renderResult('#pvpResult', false, 'Wrong. Try again.');
        else if (round.completed && round.title) renderResult('#pvpResult', false, 'Round over. Correct answer: ' + round.title);
        else setNeutralResult('#pvpResult', match.sudden_death ? 'Sudden Death: first correct answer wins the match.' : 'First correct answer gets 10 points. You can try as often as you want.');
        return;
    }
    if (match.status === 'finished') {
        renderPvpPlayers(players);
        $('#pvpTimer').text('Match finished');
        $('#pvpGuess,#submitPvpAnswerBtn').prop('disabled', true);
        const winner = players.find(p => p.id === match.winner_user_id);
        if (winner) {
            if (data.ranking_change) {
                renderResult('#pvpResult', true, winner.name + ' won the match.');
                renderPvpRankResult(data, winner);
            } else {
                setNeutralResult('#pvpResult', 'Calculating ranking...');
                $('#pvpRankResult').addClass('hidden').empty();
                hidePvpEndModal();
            }
        } else {
            setNeutralResult('#pvpResult', 'Draw. Same score.');
            $('#pvpRankResult').addClass('hidden').empty();
            hidePvpEndModal();
        }
    }
}

function loadPvpState() {
    if (!currentPvpCode) return;
    pvpStateRequest(currentPvpCode).done(d => {
        renderPvpState(d);
        loadPvpChat();
    }).fail(x => {
        const msg = x.responseJSON?.error || 'PvP state could not be loaded. Retrying...';
        renderResult(currentPvpCode ? '#pvpResult' : '#pvpLobbyResult', false, msg);
        if (x.status === 401 || x.status === 403 || x.status === 404) stopPvpPolling();
    });
}

function renderPvpChat(messages) {
    if (!Array.isArray(messages) || !messages.length) return;
    const box = $('#pvpChatMessages');
    messages.forEach(m => {
        pvpChatLastId = Math.max(pvpChatLastId, Number(m.id) || 0);
        $('<div>').addClass('chat-message').append($('<strong>').text(m.name)).append($('<span>').text(m.message)).appendTo(box);
    });
    box.scrollTop(box.prop('scrollHeight'));
}

function loadPvpChat() {
    if (!currentPvpCode) return;
    pvpChatListRequest(currentPvpCode).done(d => renderPvpChat(d.messages || []));
}

function sendPvpChat() {
    const m = $('#pvpChatInput').val().trim();
    if (!currentPvpCode || !m) return;
    pvpRequest('pvp-chat-send', 'POST', {
        code: currentPvpCode,
        message: m
    }).done(() => {
        $('#pvpChatInput').val('');
        setNeutralResult('#pvpChatResult', '');
        loadPvpChat();
    }).fail(x => renderResult('#pvpChatResult', false, x.responseJSON?.error || 'Message could not be sent.'));
}

function returnPvpLobby(message) {
    stopPvpPolling();
    stopPvpRematchWait();
    currentPvpCode = '';
    clearPvpSuggestions();
    hidePvpEndModal();
    $('#pvpLobbyPanel').removeClass('hidden');
    $('#pvpWaitingPanel,#pvpMatchPanel,#pvpChatPanel').addClass('hidden');
    $('#pvpRoomCode').val('');
    $('#pvpRankResult').addClass('hidden').empty();
    setNeutralResult('#pvpLobbyResult', message || 'Create or join a room.');
}

function waitForPvpRematch(code) {
    stopPvpRematchWait();
    pvpRematchTimer = setInterval(() => {
        pvpStateRequest(code).done(d => {
            if (d.post_match_action === 'leave') {
                returnPvpLobby('Der andere Spieler ist zur Lobby gegangen.');
                return;
            }
            if (d.match && d.match.status !== 'finished') {
                hidePvpEndModal();
                stopPvpRematchWait();
                startPvpPolling(code);
            }
        });
    }, 1000);
}

function choosePvpPostMatch(choice) {
    if (!currentPvpCode) return;
    if (choice === 'leave') {
        pvpRequest('pvp-post-match', 'POST', {
            code: currentPvpCode,
            choice: 'leave'
        }).always(() => returnPvpLobby('Du bist zur Lobby gegangen.'));
        return;
    }
    const code = currentPvpCode;
    $('.post-match-actions button').prop('disabled', true);
    pvpRequest('pvp-post-match', 'POST', {
        code,
        choice: 'rematch'
    }).done(d => {
        if (d.action === 'waiting_rematch') {
            setNeutralResult('#pvpResult', d.message || 'Warte auf Revanche.');
            $('#pvpEndRankResult .rematch-waiting').remove();
            $('#pvpEndRankResult .post-match-actions').before($('<p>').addClass('muted rematch-waiting').text(d.message || 'Warte auf den anderen Spieler.'));
            $('#pvpEndRankResult .post-match-actions .primary-btn').prop('disabled', true);
            waitForPvpRematch(code);
            return;
        }
        hidePvpEndModal();
        renderPvpState(d);
        startPvpPolling(d.match.code);
    }).fail(x => renderResult('#pvpResult', false, x.responseJSON?.error || 'Rematch could not be started.')).always(() => $('#pvpEndRankResult .post-match-actions .ghost-btn').prop('disabled', false));
}

function leavePvpMatch() {
    if (!currentPvpCode) return;
    const code = currentPvpCode;
    $('#pvpLeaveBtn,#pvpLeaveMatchBtn').prop('disabled', true);
    pvpRequest('pvp-leave', 'POST', {
        code
    }).done(d => {
        if (d.action === 'leave') {
            returnPvpLobby('Du bist zur Lobby gegangen.');
            return;
        }
        renderPvpState(d);
        startPvpPolling(code);
    }).fail(x => renderResult('#pvpResult', false, x.responseJSON?.error || 'Leave could not be saved.')).always(() => $('#pvpLeaveBtn,#pvpLeaveMatchBtn').prop('disabled', false));
}

function loadRandomGame() {
    if (!ensureSingleSession('game')) return;
    gameWrongAttempts = 0;
    gameSolved = false;
    prepareSingleRound('game');
    $.ajax({
        url: '../backend/api/index.php?route=random-game',
        type: 'GET',
        dataType: 'json'
    }).done(d => {
        currentGameAnswer = d.title || '';
        currentGameDisplayOptions = getGameDisplayOptions(currentGameAnswer);
        currentGameAnswers = currentGameDisplayOptions.map(normalizeAnswerText);
        currentGameHint = d.hint || 'No hint';
        $('#gameImage').attr('src', d.image).attr('alt', d.title);
        $('#gameHint').text(singleHintFor('game', d, currentGameHint));
        $('#gameGuess').trigger('focus');
    }).fail(x => renderResult('#gameResult', false, 'Game could not be loaded. ' + (x.responseJSON?.error || '')));
}

function loadRandomMovie() {
    if (!ensureSingleSession('movie')) return;
    movieSolved = false;
    prepareSingleRound('movie');
    $.ajax({
        url: '../backend/api/index.php?route=random-movie',
        type: 'GET',
        dataType: 'json'
    }).done(d => {
        currentMovieAnswer = d.title || '';
        currentMovieDisplayOptions = getAnswerDisplayOptions(currentMovieAnswer);
        currentMovieAnswers = currentMovieDisplayOptions.map(normalizeAnswerText);
        $('#movieImage').attr('src', d.image).attr('alt', d.title);
        $('#movieHint').text(singleHintFor('movie', d, d.hint || 'No hint'));
        $('#movieGuess').trigger('focus');
    }).fail(x => renderResult('#movieResult', false, 'Movie could not be loaded. ' + (x.responseJSON?.error || '')));
}

function loadRandomLocation() {
    if (!ensureSingleSession('locations')) return;
    locationSolved = false;
    locationAttempts = 0;
    prepareSingleRound('locations');
    $.ajax({
        url: '../backend/api/index.php?route=random-location',
        type: 'GET',
        dataType: 'json'
    }).done(d => {
        currentLocationAnswer = d.title || '';
        currentLocationAnswers = Array.isArray(d.answers) ? d.answers : [currentLocationAnswer];
        currentLocationDisplayOptions = getAnswerDisplayOptions(currentLocationAnswer, currentLocationAnswers);
        currentLocationExtraHint = d.extra_hint || '';
        $('#locationImage').attr('src', d.image).attr('alt', d.landmark || d.title);
        $('#locationHint').text(singleHintFor('locations', d, d.hint || 'No hint'));
        $('#locationGuess').trigger('focus');
    }).fail(x => renderResult('#locationResult', false, 'Location could not be loaded. ' + (x.responseJSON?.error || '')));
}

let appReady = false,
    boundShell = false;
const pageInit = {
    home: false,
    game: false,
    pvp: false,
    movie: false,
    locations: false,
    profile: false,
    shop: false
};

function validPage(p) {
    return ['home', 'game', 'pvp', 'movie', 'locations', 'profile', 'shop'].includes(p) ? p : 'home';
}

function bindShellOnce() {
    if (boundShell) return;
    boundShell = true;
    $('[data-auth-tab]').on('click', function() {
        showAuthTab($(this).data('auth-tab'));
    });
    $(document).on('click', 'a[href*="index.html?page="]', function(e) {
        if (!appReady) return;
        const url = new URL(this.href, window.location.href);
        if (url.origin !== window.location.origin || !url.pathname.endsWith('/frontend/index.html')) return;
        e.preventDefault();
        navigateToPage(validPage(url.searchParams.get('page') || 'home'), true);
    });
    window.addEventListener('popstate', () => navigateToPage(getCurrentPage(), false));
}

function navigateToPage(page, push) {
    page = validPage(page);
    if (push) {
        history.pushState({
            page
        }, '', 'index.html?page=' + encodeURIComponent(page));
    }
    showCurrentPage();
    initCurrentPage(page);
}

function bindGameOnce() {
    if (pageInit.game) return;
    pageInit.game = true;
    $('#nextGameBtn').on('click', () => advanceSingleRound('game'));
    $('#gameGuess').on('input', renderGameOptions);
    $('#checkGameBtn').on('click', function() {
        const g = $('#gameGuess').val();
        if (!activeSingleSession('game')) {
            openSingleSettings('game');
            return;
        }
        if (!currentGameAnswer || gameSolved) return;
        if (!g) {
            renderResult('#gameResult', false, 'Type a game title first.');
            return;
        }
        registerSingleAttempt('game');
        if (isGameAnswerCorrect(g)) {
            gameSolved = true;
            $('#gameOptions').addClass('hidden').empty();
            handleSingleCorrect('game');
            return;
        }
        gameWrongAttempts++;
        renderResult('#gameResult', false, singleSession?.difficulty === 'hard' ? 'Wrong! Schwer hat keine Hinweise. Versuch es weiter.' : 'Wrong! Nutze den Hinweis und versuch es nochmal.');
    });
    $('#saveGameScoreBtn').on('click', () => saveScore('game', gameScore));
}

function bindPvpOnce() {
    if (pageInit.pvp) return;
    pageInit.pvp = true;
    $('#createPvpRoomBtn').on('click', () => {
        $('#pvpSettingsPanel').removeClass('hidden');
        setNeutralResult('#pvpLobbyResult', 'Choose your room settings, then create the room.');
    });
    $('#confirmPvpRoomBtn').on('click', createPvpRoom);
    $('#cancelPvpSettingsBtn').on('click', () => {
        $('#pvpSettingsPanel').addClass('hidden');
        setNeutralResult('#pvpLobbyResult', '');
    });
    $('#joinPvpRoomBtn').on('click', () => {
        const code = normalizeText($('#pvpRoomCode').val()).toUpperCase();
        if (!code) {
            renderResult('#pvpLobbyResult', false, 'Enter a room code first.');
            return;
        }
        setNeutralResult('#pvpLobbyResult', 'Joining room...');
        pvpRequest('pvp-join', 'POST', {
            code
        }).done(d => {
            pvpChatLastId = 0;
            $('#pvpChatMessages').empty();
            startPvpPolling(d.match.code);
        }).fail(x => renderResult('#pvpLobbyResult', false, x.responseJSON?.error || 'Room could not be joined.'));
    });
    $('#pvpReadyBtn').on('click', () => {
        if (!currentPvpCode) return;
        $('#pvpReadyBtn').prop('disabled', true);
        pvpRequest('pvp-ready', 'POST', {
            code: currentPvpCode
        }).done(renderPvpState).fail(x => renderResult('#pvpLobbyResult', false, x.responseJSON?.error || 'Ready could not be saved.')).always(() => $('#pvpReadyBtn').prop('disabled', false));
    });
    $('#submitPvpAnswerBtn').on('click', () => {
        const answer = $('#pvpGuess').val().trim();
        if (!currentPvpCode || !answer) {
            renderResult('#pvpResult', false, 'Type an answer first.');
            return;
        }
        $('#submitPvpAnswerBtn').prop('disabled', true);
        pvpRequest('pvp-submit', 'POST', {
            code: currentPvpCode,
            answer
        }).done(d => {
            clearPvpSuggestions();
            if (d.last_attempt?.is_correct) $('#pvpGuess').val('');
            else $('#pvpGuess').select();
            renderPvpState(d);
        }).fail(x => {
            renderResult('#pvpResult', false, x.responseJSON?.error || 'Answer could not be submitted.');
            loadPvpState();
        });
    });
    $('#pvpGuess').on('keydown', e => {
        if (e.key === 'Enter') $('#submitPvpAnswerBtn').trigger('click');
    }).on('input', loadPvpSuggestions);
    $('#sendPvpChatBtn').on('click', sendPvpChat);
    $('#pvpLeaveBtn,#pvpLeaveMatchBtn').on('click', leavePvpMatch);
    $('#pvpChatInput').on('keydown', e => {
        if (e.key === 'Enter') sendPvpChat();
    });
}

function bindMovieOnce() {
    if (pageInit.movie) return;
    pageInit.movie = true;
    $('#nextMovieBtn').on('click', () => advanceSingleRound('movie'));
    $('#movieGuess').on('input', renderMovieOptions);
    $('#checkMovieBtn').on('click', () => {
        const g = $('#movieGuess').val();
        if (!activeSingleSession('movie')) {
            openSingleSettings('movie');
            return;
        }
        if (!currentMovieAnswer || movieSolved) return;
        if (!normalizeAnswerText(g)) {
            renderResult('#movieResult', false, 'Type a movie title first.');
            return;
        }
        registerSingleAttempt('movie');
        if (isMovieAnswerCorrect(g)) {
            movieSolved = true;
            $('#movieOptions').addClass('hidden').empty();
            handleSingleCorrect('movie');
            return;
        }
        renderResult('#movieResult', false, singleSession?.difficulty === 'hard' ? 'Wrong! Schwer hat keine Hinweise. Versuch es weiter.' : 'Wrong! Nutze den Hinweis und versuch es nochmal.');
    });
    $('#saveMovieScoreBtn').on('click', () => saveScore('movie', movieScore));
}

function bindLocationsOnce() {
    if (pageInit.locations) return;
    pageInit.locations = true;
    $('#nextLocationBtn').on('click', () => advanceSingleRound('locations'));
    $('#locationGuess').on('input', renderLocationOptions);
    $('#requestLocationHintBtn').on('click', () => {
        $('#locationHint').text(currentLocationExtraHint);
        $('#requestLocationHintBtn').addClass('hidden');
    });
    $('#checkLocationBtn').on('click', () => {
        const g = $('#locationGuess').val();
        if (!activeSingleSession('locations')) {
            openSingleSettings('locations');
            return;
        }
        if (!currentLocationAnswer || locationSolved) return;
        if (!normalizeAnswerText(g)) {
            renderResult('#locationResult', false, 'Type a city name first.');
            return;
        }
        registerSingleAttempt('locations');
        if (isLocationAnswerCorrect(g)) {
            locationSolved = true;
            $('#locationOptions').addClass('hidden').empty();
            handleSingleCorrect('locations');
            return;
        }
        locationAttempts++;
        renderResult('#locationResult', false, singleSession?.difficulty === 'hard' ? 'Wrong! Schwer hat keine Hinweise. Versuch es weiter.' : 'Wrong! Nutze den Hinweis und versuch es nochmal.');
    });
    $('#saveLocationScoreBtn').on('click', () => saveScore('locations', locationScore));
}

function bindProfileShopOnce() {
    if (pageInit.profile && pageInit.shop) return;
    $('#saveProfileBtn').off('click.profile').on('click.profile', saveProfile);
    $('#shopCategory').off('change.shop').on('change.shop', () => renderShopItems(currentShopData?.items || []));
    $('#leaderboardMode,#singleLeaderboardMode').off('change.leaderboard').on('change.leaderboard', loadLeaderboards);
    pageInit.profile = true;
    pageInit.shop = true;
}

function initCurrentPage(page) {
    page = validPage(page || getCurrentPage());
    if (page !== 'pvp') stopPvpPolling();
    if (page === 'home') {
        loadLeaderboards();
        setTimeout(loadLeaderboards, 250);
    }
    if (page === 'game') {
        bindGameOnce();
        activeSingleSession('game') ? updateGameRanking() : openSingleSettings('game');
    }
    if (page === 'pvp') {
        bindPvpOnce();
        const room = (getUrlParameter('room') || currentPvpCode || '').toUpperCase();
        if (room) startPvpPolling(room);
    }
    if (page === 'movie') {
        bindMovieOnce();
        activeSingleSession('movie') ? updateMovieRanking() : openSingleSettings('movie');
    }
    if (page === 'locations') {
        bindLocationsOnce();
        activeSingleSession('locations') ? updateLocationRanking() : openSingleSettings('locations');
    }
    if (page === 'profile') {
        bindProfileShopOnce();
        loadProfile();
    }
    if (page === 'shop') {
        bindProfileShopOnce();
        loadShop();
    }
}
$(function() {
    bindShellOnce();
    bindSingleSettingsOnce();
    $.ajax({
        url: '../backend/api/session.php',
        type: 'GET',
        dataType: 'json',
        cache: false
    }).done(function(d) {
        if (!d.authenticated) {
            showLogin();
            return;
        }
        appReady = true;
        showApp();
        bindProfileShopOnce();
        loadShop();
        loadProfile();
        initCurrentPage(window.personAPage);
    }).fail(showLogin);
});
// Restored profile/shop/leaderboard layer
let currentUserProfile = null,
    currentShopData = null;
const DEFAULT_AVATAR = {
    skin: 'blue',
    eyes: 'calm',
    mouth: 'smile',
    accessory: 'none',
    shape: 'round',
    hair: 'none',
    beard: 'none',
    glasses: 'none',
    necklace: 'none',
    animation: 'none'
};
let avatarDraft = Object.assign({}, DEFAULT_AVATAR);

function getCurrentPage() {
    const p = getUrlParameter('page') || 'home';
    return ['home', 'game', 'pvp', 'movie', 'locations', 'profile', 'shop'].includes(p) ? p : 'home';
}

function apiRequest(route, method, data) {
    return $.ajax({
        url: '../backend/api/index.php?route=' + encodeURIComponent(route),
        type: method || 'GET',
        dataType: 'json',
        contentType: 'application/json',
        data: data ? JSON.stringify(data) : undefined
    });
}

function normalizeAvatar(a) {
    return Object.assign({}, DEFAULT_AVATAR, a || {});
}

function avatarAnimationValue(a) {
    return String((a && a.animation) || 'none').trim() || 'none';
}

function avatarClass(a, size) {
    a = normalizeAvatar(a);
    return ['avatar', size || '', 'avatar-skin-' + (a.skin || 'blue'), 'avatar-eyes-' + (a.eyes || 'calm'), 'avatar-mouth-' + (a.mouth || 'smile'), 'avatar-accessory-' + (a.accessory || 'none'), 'avatar-shape-' + (a.shape || 'round'), 'avatar-hair-' + (a.hair || 'none'), 'avatar-beard-' + (a.beard || 'none'), 'avatar-glasses-' + (a.glasses || 'none'), 'avatar-necklace-' + (a.necklace || 'none'), 'avatar-animation-' + avatarAnimationValue(a)].join(' ');
}

function avatarForPlayer(p) {
    const a = normalizeAvatar(p?.avatar || {}),
        id = Number(p?.user_id ?? p?.id ?? 0),
        me = Number(currentUserProfile?.id || 0);
    return id && me && id === me ? normalizeAvatar(Object.assign({}, a, avatarDraft)) : a;
}

function applyAvatarElement(el, a, size) {
    a = normalizeAvatar(a);
    const anim = avatarAnimationValue(a);
    $(el).attr({
        'class': avatarClass(a, size),
        'data-avatar-animation': anim
    }).empty().append($('<span>').addClass('avatar-hair-shape')).append($('<span>').addClass('avatar-accessory-shape')).append($('<span>').addClass('avatar-face').append($('<i>')).append($('<i>'))).append($('<span>').addClass('avatar-glasses-shape')).append($('<span>').addClass('avatar-mouth')).append($('<span>').addClass('avatar-beard-shape')).append($('<span>').addClass('avatar-necklace-shape'));
}

function isUnlocked(field, value) {
    const list = currentShopData?.unlocked?.[field];
    return !Array.isArray(list) || list.includes(value);
}

function avatarFieldLabel(field) {
    return ({
        skin: 'Skin',
        shape: 'Form',
        eyes: 'Augen',
        mouth: 'Mund',
        accessory: 'Accessoire',
        hair: 'Haare',
        beard: 'Bart',
        glasses: 'Brille',
        necklace: 'Kette',
        animation: 'Animation'
    })[field] || field;
}

function avatarValueLabel(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function renderAvatarEditor() {
    const groups = {
        skin: ['blue', 'green', 'red', 'gold', 'purple', 'ice', 'shadow', 'ruby', 'galaxy'],
        shape: ['round', 'oval', 'square', 'hex'],
        eyes: ['calm', 'focus', 'happy'],
        mouth: ['smile', 'serious', 'open'],
        accessory: ['none', 'cap', 'crown', 'headset', 'beanie', 'fedora', 'halo', 'mask', 'wizard', 'legend_crown'],
        hair: ['none', 'short', 'spiky', 'curly', 'side', 'mohawk', 'long', 'flame', 'wave', 'legend_flame'],
        beard: ['none', 'stubble', 'goatee', 'full', 'royal', 'diamond'],
        glasses: ['none', 'round', 'square', 'visor', 'star', 'neon', 'laser'],
        necklace: ['none', 'chain', 'clock', 'diamond', 'legend_clock'],
        animation: ['none', 'bounce', 'hair_wave', 'glow', 'legend_aura']
    };
    const box = $('#avatarEditor').empty();
    Object.entries(groups).forEach(([field, values]) => {
        box.append($('<label>').addClass('input-label').text(avatarFieldLabel(field)));
        const row = $('<div>').addClass('avatar-options').attr('data-avatar-field', field);
        values.forEach(v => $('<button>').attr('type', 'button').addClass('avatar-option').toggleClass('is-active', avatarDraft[field] === v).toggleClass('is-locked', !isUnlocked(field, v)).prop('disabled', !isUnlocked(field, v)).text(avatarValueLabel(v)).data('avatar-value', v).on('click', function() {
            avatarDraft[field] = v;
            renderAvatarEditor();
            updateProfilePreview();
        }).appendTo(row));
        box.append(row);
    });
}

function updateProfilePreview() {
    const name = $('#profileGameName').val()?.trim() || currentUserProfile?.game_name || 'Player',
        anim = avatarAnimationValue(avatarDraft);
    $('#profilePreviewName,#topbarGameName').text(name);
    applyAvatarElement('#profileAvatarPreview', avatarDraft, 'avatar-large');
    applyAvatarElement('#topbarAvatar', avatarDraft, 'avatar-small');
    $('.player-chip').toggleClass('has-avatar-animation', anim !== 'none').attr('data-avatar-animation', anim);
}

function loadProfile() {
    return apiRequest('profile', 'GET').done(d => {
        currentUserProfile = d.profile;
        avatarDraft = normalizeAvatar(currentUserProfile.avatar || {});
        $('#profileGameName').val(currentUserProfile.game_name || '');
        renderAvatarEditor();
        updateProfilePreview();
    });
}

function saveProfile() {
    apiRequest('profile-save', 'POST', {
        game_name: $('#profileGameName').val(),
        avatar: avatarDraft
    }).done(d => {
        currentUserProfile = d.profile;
        avatarDraft = normalizeAvatar(currentUserProfile.avatar || {});
        renderAvatarEditor();
        updateProfilePreview();
        loadLeaderboards();
        renderResult('#profileResult', true, 'Profil gespeichert.');
    }).fail(x => renderResult('#profileResult', false, x.responseJSON?.error || 'Profil konnte nicht gespeichert werden.'));
}

function formatRarity(r) {
    return ({
        normal: 'Normal',
        rare: 'Rare',
        super_rare: 'Super Rare',
        episch: 'Episch',
        legendaer: 'Legendaer'
    })[r] || r;
}

function shopClassToken(value) {
    return String(value || 'none').toLowerCase().replace(/[^a-z0-9_-]+/g, '_');
}

function renderShopItems(items) {
    const cat = $('#shopCategory').val() || 'all';
    const box = $('#shopItems').empty();
    (items || []).filter(i => cat === 'all' || i.field === cat).forEach(item => {
        const owned = Number(item.owned) === 1;
        const preview = Object.assign({}, avatarDraft);
        preview[item.field] = item.value;
        $('<div>').addClass('shop-item rarity-item-' + shopClassToken(item.rarity) + ' shop-field-' + shopClassToken(item.field) + ' shop-value-' + shopClassToken(item.value)).toggleClass('is-owned', owned).append($('<span>').each(function() {
            applyAvatarElement(this, preview, 'avatar-small');
        })).append($('<div>').addClass('shop-item-main').append($('<strong>').text(item.name)).append($('<small>').addClass('rarity-' + item.rarity).text(avatarFieldLabel(item.field) + ' | ' + formatRarity(item.rarity)))).append($('<button>').attr('type', 'button').addClass(owned ? 'ghost-btn' : 'primary-btn').prop('disabled', owned).text(owned ? 'Owned' : item.price + ' coins').on('click', () => buyShopItem(item.id))).appendTo(box);
    });
}

function updateShop(d) {
    currentShopData = Object.assign({}, currentShopData || {}, d || {});
    $('#topbarCoins,#shopCoins').text(currentShopData.coins || 0);
    $('#shopPvpPlacement').text((currentShopData.season?.placement_pvp_remaining || 0) + '/5');
    $('#shopSinglePlacement').text((currentShopData.season?.placement_single_remaining || 0) + '/5');
    renderAvatarEditor();
    if (window.personAPage === 'shop') renderShopItems(currentShopData.items || []);
}

function loadShop() {
    return apiRequest('shop', 'GET').done(updateShop);
}

function buyShopItem(id) {
    apiRequest('shop-buy', 'POST', {
        item_id: id
    }).done(d => {
        if (d.bought?.field && d.bought?.value) {
            avatarDraft[d.bought.field] = d.bought.value;
        }
        updateShop(d);
        loadProfile().always(loadLeaderboards);
        renderResult('#shopResult', true, 'Item gekauft und ausgeruestet.');
    }).fail(x => renderResult('#shopResult', false, x.responseJSON?.error || 'Nicht genug Coins.'));
}

function renderLeaderboardInto(target, players, single) {
    const box = $(target).empty();
    if (!players || !players.length) {
        box.append($('<div>').addClass('leaderboard-empty').text('Noch keine Ranking-Daten.'));
        return;
    }
    players.forEach(p => {
        const record = single ? ('Matches ' + (p.matches || 0) + ' | Correct ' + (p.correct || 0) + ' | Skips ' + (p.skipped || 0)) : ('Wins ' + (p.wins || 0) + ' | Losses ' + (p.losses || 0)),
            a = avatarForPlayer(p),
            anim = avatarAnimationValue(a);
        $('<div>').addClass('leaderboard-row').toggleClass('has-avatar-animation', anim !== 'none').attr('data-avatar-animation', anim).append($('<span>').addClass('leaderboard-position').text('#' + p.position)).append($('<span>').each(function() {
            applyAvatarElement(this, a, 'avatar-small');
        })).append($('<span>').addClass('leaderboard-player').append($('<strong>').text(p.name)).append($('<small>').addClass('leaderboard-rank-line').append(rankIcon(p.rank, 'rank-icon-inline')).append($('<span>').text(p.rank)).append($('<em>').text('+' + (p.monthly_coins || 0) + ' coins'))).append($('<small>').addClass('leaderboard-record').text(record))).append($('<span>').addClass('leaderboard-score').append($('<b>').text(p.points)).append($('<small>').text('pts'))).appendTo(box);
    });
}

function loadLeaderboards() {
    $('#leaderboardMode,#singleLeaderboardMode').each(function() {
        if (!$(this).val()) $(this).val('global');
    });
    const pvpMode = $('#leaderboardMode').val() || 'global';
    const singleMode = $('#singleLeaderboardMode').val() || 'global';
    $.ajax({
        url: "../backend/api/index.php?route=pvp-leaderboard&mode=" + encodeURIComponent(pvpMode),
        type: "GET",
        dataType: "json"
    }).done(d => renderLeaderboardInto("#leaderboardList", d.players, false)).fail(x => $('#leaderboardList').empty().append($('<div>').addClass('leaderboard-empty').text(x.responseJSON?.error || 'Leaderboard konnte nicht geladen werden.')));
    $.ajax({
        url: "../backend/api/index.php?route=single-leaderboard&mode=" + encodeURIComponent(singleMode),
        type: "GET",
        dataType: "json"
    }).done(d => renderLeaderboardInto("#singleLeaderboardList", d.players, true)).fail(x => $('#singleLeaderboardList').empty().append($('<div>').addClass('leaderboard-empty').text(x.responseJSON?.error || 'Leaderboard konnte nicht geladen werden.')));
}
