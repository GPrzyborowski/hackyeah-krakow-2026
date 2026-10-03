package com.example.momjobs.data.remote.models

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

@JsonClass(generateAdapter = true)
data class GeminiRequest(
    val contents: List<Content>
)

@JsonClass(generateAdapter = true)
data class Content(
    val parts: List<Part>
)

@JsonClass(generateAdapter = true)
data class Part(
    val text: String
)

@JsonClass(generateAdapter = true)
data class GeminiResponse(
    val candidates: List<Candidate>? = null,
    val promptFeedback: PromptFeedback? = null
)

@JsonClass(generateAdapter = true)
data class Candidate(
    val content: Content,
    val finishReason: String? = null,
    val index: Int? = null,
    val safetyRatings: List<SafetyRating>? = null
)

@JsonClass(generateAdapter = true)
data class SafetyRating(
    val category: String,
    val probability: String
)

@JsonClass(generateAdapter = true)
data class PromptFeedback(
    val safetyRatings: List<SafetyRating>
)
