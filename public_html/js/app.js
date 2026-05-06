document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('sentence-form');
    var answerField = document.getElementById('sentence-answer');
    var feedbackCard = document.getElementById('feedback-card');
    var feedbackMessage = document.getElementById('feedback-message');
    var correctAnswer = document.getElementById('correct-answer');
    var incorrectSentence = document.getElementById('incorrect-sentence');
    var sentenceHint = document.getElementById('sentence-hint');
    var streakValue = document.getElementById('streak-value');
    var bestStreakValue = document.getElementById('best-streak-value');
    var answeredCountValue = document.getElementById('answered-count-value');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        var formData = new FormData();
        formData.append('answer', answerField.value);

        fetch('api/check_sentence.php', {
            method: 'POST',
            body: formData
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    feedbackCard.classList.remove('hidden');
                    feedbackMessage.textContent = data.message || 'Something went wrong.';
                    correctAnswer.classList.add('hidden');
                    return;
                }

                streakValue.textContent = data.streak;
                bestStreakValue.textContent = data.best_streak;
                answeredCountValue.textContent = data.answered_count;

                feedbackCard.classList.remove('hidden');
                feedbackMessage.textContent = data.message;

                if (data.is_correct) {
                    correctAnswer.classList.add('hidden');
                    correctAnswer.textContent = '';
                } else {
                    correctAnswer.classList.remove('hidden');
                    correctAnswer.textContent = 'Correct answer: ' + data.correct_answer;
                }

                if (data.next_sentence) {
                    incorrectSentence.textContent = data.next_sentence.incorrect;
                    sentenceHint.textContent = data.next_sentence.hint;
                }

                answerField.value = '';
                answerField.focus();
            })
            .catch(function () {
                feedbackCard.classList.remove('hidden');
                feedbackMessage.textContent = 'The request failed. Please try again.';
                correctAnswer.classList.add('hidden');
            });
    });
});
