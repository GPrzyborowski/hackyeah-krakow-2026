package com.example.momjobs.data.remote.models

import com.squareup.moshi.Json
import com.squareup.moshi.JsonClass

@JsonClass(generateAdapter = true)
data class RegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    @Json(name = "password_confirmation") val passwordConfirmation: String
)

@JsonClass(generateAdapter = true)
data class LoginRequest(
    val email: String,
    val password: String,
    @Json(name = "device_name") val deviceName: String,
    val code: String? = null,
    @Json(name = "recovery_code") val recoveryCode: String? = null
)

@JsonClass(generateAdapter = true)
data class AuthResponse(
    val token: String,
    val user: UserDto
)

@JsonClass(generateAdapter = true)
data class UserDto(
    val id: Int,
    val name: String,
    val email: String,
    val role: String? = null,
    @Json(name = "company_id") val companyId: Int? = null,
    @Json(name = "email_verified_at") val emailVerifiedAt: String? = null,
    @Json(name = "email_verified") val emailVerified: Boolean? = null,
    @Json(name = "push_enabled") val pushEnabled: Boolean? = null,
    @Json(name = "candidate_profile") val candidateProfile: CandidateProfileDto? = null
)

@JsonClass(generateAdapter = true)
data class CandidateProfileDto(
    val id: Int? = null,
    val published: Boolean? = null,
    @Json(name = "onboarding_step") val onboardingStep: Int? = null
)

@JsonClass(generateAdapter = true)
data class UserMeResponse(
    val data: UserDto? = null,
    val id: Int? = null,
    val name: String? = null,
    val email: String? = null,
    val role: String? = null,
    @Json(name = "company_id") val companyId: Int? = null,
    @Json(name = "email_verified_at") val emailVerifiedAt: String? = null,
    @Json(name = "email_verified") val emailVerified: Boolean? = null,
    @Json(name = "push_enabled") val pushEnabled: Boolean? = null,
    @Json(name = "candidate_profile") val candidateProfile: CandidateProfileDto? = null
) {
    fun extractUser(): UserDto? {
        return data ?: if (id != null && name != null && email != null) {
            UserDto(
                id = id,
                name = name,
                email = email,
                role = role,
                companyId = companyId,
                emailVerifiedAt = emailVerifiedAt,
                emailVerified = emailVerified,
                pushEnabled = pushEnabled,
                candidateProfile = candidateProfile
            )
        } else null
    }
}

@JsonClass(generateAdapter = true)
data class ApiErrorResponse(
    val message: String? = null,
    val errors: Map<String, List<String>>? = null,
    @Json(name = "two_factor_required") val twoFactorRequired: Boolean? = null
)
