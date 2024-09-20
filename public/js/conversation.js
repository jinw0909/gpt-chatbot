// Utility to make API requests
async function apiRequest(url, method = 'GET', body = null, headers = {}) {
    try {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                ...headers,
            },
        };

        if (body) {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(response.statusText);
        }
        return await response.json();
    } catch (error) {
        console.error('API Request Error:', error);
        throw error;
    }
}

// Utility to log error and create error message in the DOM
function displayErrorMessage(message, container) {
    const errorMessageDiv = document.createElement('div');
    errorMessageDiv.className = 'error-message';
    errorMessageDiv.textContent = message;
    container.appendChild(errorMessageDiv);
}

// Utility to scroll to the bottom of an element
function scrollToBottom(element) {
    element.scrollTop = element.scrollHeight;
}

// Utility to toggle element visibility
function toggleVisibility(element, show = true) {
    element.style.display = show ? 'block' : 'none';
}

// Utility to create a DOM element with optional content and class
function createElement(tag, content = '', className = '') {
    const element = document.createElement(tag);
    element.textContent = content;
    if (className) {
        element.className = className;
    }
    return element;
}

// Fetch and display user's charge
async function fetchUserCharge() {
    const chargeDisplay = document.querySelector('.remaining');
    try {
        const chargeData = await apiRequest('/user/1/get-charge');
        const formattedCharge = parseFloat(chargeData.charge).toFixed(3);
        chargeDisplay.textContent = `$${formattedCharge}`;
    } catch (error) {
        displayErrorMessage('Error fetching tokens.', chargeDisplay);
    }
}

// Add user charge and refresh the displayed charge
async function addUserCharge() {
    try {
        await apiRequest('/user/1/add-charge', 'POST', { amount: 10 }, { 'X-CSRF-TOKEN': '{{ csrf_token() }}' });
        await fetchUserCharge();
    } catch (error) {
        displayErrorMessage('Error adding charge.', document.querySelector('.remaining'));
    }
}

// Event listener for showing the chat input area
function toggleChatInput(isOpen) {
    const inputWrapper = document.getElementById('input-wrapper');
    const inputOpen = document.querySelector('.input-open');
    const inputClose = document.querySelector('.input-close');

    inputWrapper.style.maxHeight = isOpen ? "175px" : "0";
    toggleVisibility(inputOpen, !isOpen);
    toggleVisibility(inputClose, isOpen);
}

// Event handler for sending a message
async function sendMessage(customMessage = '') {
    console.log("sendMessage()");
    const messageInput = document.getElementById('message-input');
    const chatBox = document.getElementById('chat-box');
    const generatingMessage = document.getElementById('generating-message');
    let message = customMessage || messageInput.value;

    if (message === '') {
        console.log("message is empty");
        return;
    }

    messageInput.value = '';  // Clear the input field if not custom message
    messageInput.readOnly = true;
    messageInput.classList.add('locked');
    toggleVisibility(generatingMessage, true);

    // Add user's message to chat box
    const userMessageDiv = createElement('div', message, 'user');
    chatBox.appendChild(createElement('div', '', 'message right').appendChild(userMessageDiv));
    scrollToBottom(chatBox);

    try {
        const response = await apiRequest(processMessageUrl, 'POST', {
            message,
            userId: document.getElementById('user-id').value,
            maxUsage: document.getElementById('max-usage').value,
            conversation,
            symbols: recommendedSymbols.flat(),
            articles: revealedArticles,
            lang: selectedLanguage,
        }, { 'X-CSRF-TOKEN': '{{ csrf_token() }}' });

        handleResponse(response, chatBox);  // Function to handle different response types
    } catch (error) {
        displayErrorMessage('Something probably went wrong', chatBox);
    } finally {
        messageInput.readOnly = false;
        messageInput.classList.remove('locked');
        toggleVisibility(generatingMessage, false);
    }
}

// Handle different types of responses
function handleResponse(data, chatBox) {
    const parsedResponse = JSON.parse(data.responseText);

    switch (parsedResponse.data.format_type) {
        case 'crypto_recommendations':
            handleCryptoRecommendations(parsedResponse, chatBox);
            break;
        case 'crypto_analyses':
            handleCryptoAnalyses(parsedResponse, chatBox);
            break;
        case 'articles':
            handleArticles(parsedResponse, chatBox);
            break;
        case 'default':
            displayAssistantMessage(parsedResponse.data.content, chatBox);
            break;
        default:
            console.error('Unknown response format type');
    }
    attachQuestionListeners();
}

// Display assistant's message in the chat box
function displayAssistantMessage(content, chatBox) {
    const assistantMessageDiv = createElement('div', content.replace(/\n/g, '<br>'), 'assistant');
    const messageWrapper = createElement('div', '', 'message left');
    messageWrapper.appendChild(assistantMessageDiv);
    chatBox.appendChild(messageWrapper);
}

// Modal handling
function handleModal() {
    const modal = document.getElementById("myModal");
    const openBtn = document.querySelector(".open-btn");
    const closeBtn = document.querySelector(".close");

    openBtn.onclick = () => { modal.style.display = "block"; };
    closeBtn.onclick = () => { modal.style.display = "none"; };
}

// Execute the question by sending the text content as a message
function executeQuestion(elem) {
    if (elem.textContent !== '') {
        const message = elem.textContent;
        console.log("User message:", message);
        sendMessage(message);
    }
}

// Attach event listeners to elements with the class 'question' to execute the question
function attachQuestionListeners() {
    const questionElements = document.querySelectorAll('.question');
    questionElements.forEach((element) => {
        element.addEventListener('click', function () {
            executeQuestion(this);
        });
    });
}

// Initialize event listeners and load initial data
document.addEventListener('DOMContentLoaded', function () {
    fetchUserCharge();
    document.getElementById('send-button').addEventListener('click', () => sendMessage());
    document.getElementById('add-button').addEventListener('click', addUserCharge);
    document.getElementById('input-open').addEventListener('click', () => toggleChatInput(true));
    document.getElementById('input-close').addEventListener('click', () => toggleChatInput(false));
    handleModal();
    attachQuestionListeners();
});


// Handle crypto recommendations
function handleCryptoRecommendations(parsedResponse, chatBox) {
    const recommendations = parsedResponse.data.content;
    const symbols = [];

    recommendations.forEach(parsed => {
        parsed.symbol = parsed.symbol.toUpperCase();
        symbols.push(parsed.symbol);

        const symbolDiv = createElement('div', `${parsed.symbol}`, '');
        symbolDiv.style.color = 'aqua';

        const datetimeDiv = createElement('div', `${parsed.datetime}`, '');

        const gapDiv = createElement('div', formatTimeGap(parsed.time_gap), '');
        gapDiv.style.color = '#bbb';

        const imageElement = createElement('img', '', '');
        imageElement.src = parsed.image_url;
        imageElement.style.width = '100%';
        imageElement.style.borderRadius = '8px';

        const contentDiv = createElement('div', `${parsed.recommended_reason_translated}`, '');

        const assistantDiv = createElement('div', '', 'assistant');
        assistantDiv.appendChild(symbolDiv);
        assistantDiv.appendChild(datetimeDiv);
        assistantDiv.appendChild(gapDiv);
        assistantDiv.appendChild(imageElement);
        assistantDiv.appendChild(contentDiv);

        const wrapperDiv = createElement('div', '', 'message');
        wrapperDiv.appendChild(assistantDiv);
        chatBox.appendChild(wrapperDiv);
    });

    recommendedSymbols.push(symbols);
}

// Handle crypto analyses
function handleCryptoAnalyses(parsedResponse, chatBox) {
    const analyses = parsedResponse.data.content;
    analyses.forEach(parsed => {
        const canvas = document.createElement('canvas');
        const symbolDiv = createElement('div', parsed.symbol.toUpperCase(), '');
        symbolDiv.style.color = 'aqua';

        const priceDiv = createElement('div', `$${Number(parsed.symbol_data.symbol_price).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 7 })}`, '');
        const timeDiv = createElement('div', parsed.symbol_data.record_time, '');
        const gapDiv = createElement('div', formatTimeGap(parsed.symbol_data.time_gap), '');
        gapDiv.style.color = '#bbb';
        const analysisDiv = createElement('div', parsed.analysis_translated, '');

        const assistantDiv = createElement('div', '', 'assistant');
        assistantDiv.appendChild(symbolDiv);
        assistantDiv.appendChild(priceDiv);
        assistantDiv.appendChild(timeDiv);
        assistantDiv.appendChild(gapDiv);
        assistantDiv.appendChild(canvas);
        assistantDiv.appendChild(analysisDiv);

        const wrapperDiv = createElement('div', '', 'message');
        wrapperDiv.appendChild(assistantDiv);
        chatBox.appendChild(wrapperDiv);

        // Draw chart for price and score movements
        const timeLabels = parsed.crypto_data.map(item => item.datetime);
        const scoreMovement = parsed.crypto_data.map(item => item.score);
        const priceMovement = parsed.crypto_data.map(item => item.price);
        drawChart(priceMovement, scoreMovement, timeLabels, canvas);
    });
}

// Handle articles
function handleArticles(parsedResponse, chatBox) {
    const articles = parsedResponse.data.content;

    articles.forEach(parsed => {
        const titleDiv = createElement('div', parsed.title, '');
        titleDiv.style.color = 'aqua';

        const datetimeDiv = createElement('div', parsed.datetime, '');
        const timegapDiv = createElement('div', formatTimeGap(parsed.time_gap), '');
        timegapDiv.style.color = '#bbb';

        const imageElement = createElement('img', '', '');
        imageElement.src = parsed.image_url;
        imageElement.style.width = '100%';
        imageElement.style.borderRadius = '8px';

        const contentDiv = createElement('div', parsed.content, '');

        const wrapperDiv = createElement('div', '', 'message left');
        const assistantDiv = createElement('div', '', 'assistant');
        assistantDiv.appendChild(titleDiv);
        assistantDiv.appendChild(datetimeDiv);
        assistantDiv.appendChild(timegapDiv);
        assistantDiv.appendChild(imageElement);
        assistantDiv.appendChild(contentDiv);

        wrapperDiv.appendChild(assistantDiv);
        chatBox.appendChild(wrapperDiv);
    });
}

// Format time gaps for display
function formatTimeGap(timeGap) {
    const timeTexts = timeUnits[selectedLanguage];
    let timeGapText = '';

    if (timeGap.hours) {
        timeGapText += `${timeGap.hours}${timeGap.hours === 1 ? timeTexts.hour : timeTexts.hours}`;
    }
    if (timeGap.minutes) {
        timeGapText += `${timeGap.minutes}${timeGap.minutes === 1 ? timeTexts.minute : timeTexts.minutes}`;
    }
    return timeGapText ? timeGapText + timeTexts.recommend : '';
}


