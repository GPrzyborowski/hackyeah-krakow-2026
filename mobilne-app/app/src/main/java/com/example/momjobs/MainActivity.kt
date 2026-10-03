package com.example.momjobs

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import com.example.momjobs.ui.MainScreen
import com.example.momjobs.ui.theme.WracamTheme
import com.example.momjobs.ui.viewmodels.*

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        val app = application as MomjobsApplication
        val candidateViewModel: CandidateViewModel by viewModels {
            CandidateViewModelFactory(app.repository)
        }
        val jobViewModel: JobViewModel by viewModels {
            JobViewModelFactory(app.repository)
        }
        val reviewViewModel: ReviewViewModel by viewModels {
            ReviewViewModelFactory(app.repository)
        }
        val aiAssistantViewModel: AiAssistantViewModel by viewModels {
            AiAssistantViewModelFactory(app.repository)
        }
        val invitationViewModel: InvitationViewModel by viewModels {
            InvitationViewModelFactory(app.repository)
        }

        enableEdgeToEdge()
        setContent {
            WracamTheme {
                MainScreen(
                    candidateViewModel = candidateViewModel,
                    jobViewModel = jobViewModel,
                    reviewViewModel = reviewViewModel,
                    aiAssistantViewModel = aiAssistantViewModel,
                    invitationViewModel = invitationViewModel
                )
            }
        }
    }
}
