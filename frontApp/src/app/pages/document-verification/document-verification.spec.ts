import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DocumentVerification } from './document-verification';

describe('DocumentVerification', () => {
  let component: DocumentVerification;
  let fixture: ComponentFixture<DocumentVerification>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DocumentVerification],
    }).compileComponents();

    fixture = TestBed.createComponent(DocumentVerification);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
