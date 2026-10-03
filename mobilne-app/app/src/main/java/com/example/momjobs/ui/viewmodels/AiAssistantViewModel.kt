package com.example.momjobs.ui.viewmodels

import androidx.compose.runtime.mutableStateListOf
import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.momjobs.BuildConfig
import com.example.momjobs.data.local.entities.JobAd
import com.example.momjobs.data.repository.MomjobsRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class ChatMessage(
    val text: String,
    val isUser: Boolean,
    val timestamp: Long = System.currentTimeMillis()
)

class AiAssistantViewModel(private val repository: MomjobsRepository) : ViewModel() {

    private val _messages = mutableStateListOf<ChatMessage>()
    val messages: List<ChatMessage> = _messages

    private val _isTyping = MutableStateFlow(false)
    val isTyping: StateFlow<Boolean> = _isTyping.asStateFlow()

    private val apiKey = BuildConfig.GEMINI_API_KEY

    init {
        _messages.add(ChatMessage("Witaj! Jestem Twoim asystentem Wracam. Specjalizuję się w polskim prawie pracy i uprawnieniach rodzicielskich. Pomogę Ci odnaleźć się w przepisach dotyczących urlopów macierzyńskich, rodzicielskich czy elastycznych form zatrudnienia. O co chcesz zapytać?", false))
    }

    fun sendMessage(text: String) {
        if (text.isBlank()) return

        _messages.add(ChatMessage(text, true))
        
        if (apiKey.isBlank()) {
            _messages.add(ChatMessage("Przepraszam, ale moje systemy nie są jeszcze skonfigurowane (brak klucza API). Proszę skontaktuj się z administratorem.", false))
            return
        }

        viewModelScope.launch {
            _isTyping.value = true
            
            // Specialized System Prompt for Polish Labor Rights
            val systemPrompt = """
                Jesteś ekspertem ds. polskiego prawa pracy, specjalizującym się w uprawnieniach matek i ojców w aplikacji 'Wracam'. 
                Twoim zadaniem jest udzielanie rzetelnych, merytorycznych i wspierających odpowiedzi w języku polskim.
                Skup się na:
                - Kodeksie Pracy i aktualnych przepisach dotyczących rodzicielstwa (Dyrektywa Work-Life Balance).
                - Urlopach: macierzyńskim, rodzicielskim, wychowawczym, ojcowskim.
                - Zasiłkach macierzyńskich i chorobowych.
                - Ochronie stosunku pracy w czasie ciąży i po powrocie.
                - Elastycznych formach pracy: pracy zdalnej, przerywanym czasie pracy, obniżeniu wymiaru etatu.
                - Przerwach na karmienie piersią.
                Zawsze podawaj konkretne terminy (np. ile tygodni trwa urlop) i zachęcaj do weryfikacji z ZUS lub PIP w sprawach indywidualnych.
                
                Użytkownik pyta: $text
            """.trimIndent()

            val response = repository.getAiResponse(systemPrompt, apiKey)
            _isTyping.value = false
            
            if (response != null) {
                _messages.add(ChatMessage(response, false))
            } else {
                _messages.add(ChatMessage("Wystąpił problem z połączeniem. Spróbuj zadać pytanie jeszcze raz za chwilę.", false))
            }
        }
    }

    fun startSmartMatch(profileName: String, bio: String, availability: String, jobs: List<JobAd>) {
        if (apiKey.isBlank()) {
            _messages.add(ChatMessage("Nie mogę teraz przeprowadzić dopasowania ofert, ponieważ brakuje mi połączenia z bazą wiedzy.", false))
            return
        }

        _messages.add(ChatMessage("Czy możesz przeanalizować mój profil i dopasować go do dostępnych ofert?", true))
        
        viewModelScope.launch {
            _isTyping.value = true
            
            val jobListString = jobs.joinToString("\n") { "Oferta ${it.id}: ${it.title} (${it.companyName}). Opis: ${it.description}. Wymagania: ${it.requirements}" }
            val prompt = """
                Jesteś asystentem dopasowania kariery w aplikacji 'Wracam'. 
                Kandydatka: $profileName. 
                Doświadczenie: $bio. 
                Dostępność: $availability.
                
                Dostępne oferty pracy:
                $jobListString
                
                Wybierz 2-3 najlepsze oferty dla tej osoby. Dla każdej z nich:
                1. Uzasadnij wybór merytorycznie.
                2. Podkreśl aspekty przyjazne mamie (np. zdalność, elastyczność).
                Używaj ciepłego, profesjonalnego języka polskiego.
            """.trimIndent()

            val response = repository.getAiResponse(prompt, apiKey)
            _isTyping.value = false
            
            if (response != null) {
                _messages.add(ChatMessage(response, false))
            } else {
                _messages.add(ChatMessage("Próbowałem dopasować oferty, ale napotkałem trudności techniczne. Spróbuj ponownie za moment.", false))
            }
        }
    }
}

class AiAssistantViewModelFactory(private val repository: MomjobsRepository) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(AiAssistantViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return AiAssistantViewModel(repository) as T
        }
        throw IllegalArgumentException("Unknown ViewModel class")
    }
}
