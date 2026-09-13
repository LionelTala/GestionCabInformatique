import { Component, signal, OnInit, ChangeDetectionStrategy, effect, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule, RouterModule],
  selector: 'app-login',
  styleUrl: './login.css',
  templateUrl: './login.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Login implements OnInit {
  // ✅ Utilisation de inject() pour garantir l'initialisation immédiate
  private auth = inject(Auth);
  private router = inject(Router);
  private toastr = inject(ToastrService);

  loginInput = '';
  password = '';
  showPassword = signal(false);
  loading = signal(false); 
  
  // ✅ Lié en toute sécurité au service initialisé
  isPageLoading = this.auth.isLoggingIn; 
  
  inputError = signal('');
  passwordError = signal('');
  errorMessage = signal('');

  constructor() {
    // ✅ Surveille de manière réactive la fin du chargement de la session sans clignotement
    effect(() => {
      if (!this.auth.isLoggingIn() && this.auth.isAuthenticated()) {
        this.router.navigate(['/dashboard']);
      }
    });
  }

  ngOnInit() {
    // Laissé vide car géré nativement par les guards et l'effet
  }

  togglePassword() {
    this.showPassword.update(v => !v);
  }

  validateInput(): boolean {
    if (!this.loginInput.trim()) {
      this.inputError.set("L'identifiant est requis");
      return false;
    }
    if (this.loginInput.trim().length < 3) {
      this.inputError.set("Minimum 3 caractères");
      return false;
    }
    this.inputError.set(''); // ✅ CORRIGÉ : Ajout du 'this.' manquant
    return true;
  }

  validatePassword(): boolean {
    if (!this.password) {
      this.passwordError.set('Le mot de passe est requis');
      return false;
    }
    if (this.password.length < 6) {
      this.passwordError.set('6 caractères minimum');
      return false;
    }
    this.passwordError.set('');
    return true;
  }

  onSubmit() {
    if (!this.validateInput() || !this.validatePassword()) return;

    this.loading.set(true);
    this.errorMessage.set('');

    this.auth.login(this.loginInput, this.password).subscribe({
      next: () => {
        this.loading.set(false);
        this.toastr.success('Connexion réussie !', 'Bienvenue');
        this.router.navigate(['/dashboard']);
      },
      error: (err: any) => {
        this.loading.set(false);
        const msg = err.error?.message || 'Identifiants incorrects'; 
        this.errorMessage.set(msg);
      }
    });
  }
}
