import { Component, signal } from '@angular/core';
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
})
export class Login {
  email = '';
  password = '';
  showPassword = signal(false);
  loading = signal(false);
  emailError = signal('');
  passwordError = signal('');
  errorMessage = signal('');

  constructor(
    private auth: Auth,
    private router: Router,
    private toastr: ToastrService
  ) {}

  togglePassword() {
    this.showPassword.update(v => !v);
  }

  validateEmail(): boolean {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (!this.email) {
      this.emailError.set('L\'email est requis');
      return false;
    }
    if (!emailRegex.test(this.email)) {
      this.emailError.set('Veuillez entrer un email valide');
      return false;
    }
    this.emailError.set('');
    return true;
  }

  validatePassword(): boolean {
    if (!this.password) {
      this.passwordError.set('Le mot de passe est requis');
      return false;
    }
    if (this.password.length < 6) {
      this.passwordError.set('Le mot de passe doit contenir au moins 6 caractères');
      return false;
    }
    this.passwordError.set('');
    return true;
  }

  onSubmit() {
    const isEmailValid = this.validateEmail();
    const isPasswordValid = this.validatePassword();

    if (!isEmailValid || !isPasswordValid) {
      this.toastr.warning('Veuillez corriger les champs indiqués', 'Formulaire invalide');
      return;
    }

    this.loading.set(true);
    this.errorMessage.set('');

    this.auth.login(this.email, this.password).subscribe({
      next: (response: any) => {
        this.loading.set(false);
        this.auth.setSession(response.token, response.user);
        this.toastr.success('Connexion réussie !', 'Bienvenue');
        this.router.navigate(['/dashboard']);
      },
      error: (err) => {
        this.loading.set(false);
        this.errorMessage.set(err.error?.message || 'Identifiants incorrects');
        this.toastr.error(this.errorMessage(), 'Erreur de connexion');
      }
    });
  }
}